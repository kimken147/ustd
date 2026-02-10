<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\TransactionCollection;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\User;
use App\Models\Channel;
use App\Models\TransactionNote;
use App\Utils\AmountDisplayTransformer;
use App\Utils\TransactionUtil;
use App\Models\FeatureToggle;
use App\Repository\FeatureToggleRepository;
use App\Services\CertificateService;
use App\Utils\DateRangeValidator;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class TransactionController extends Controller
{
    public function __construct(
        private readonly CertificateService $certificateService
    ) {
    }

    public function index(
        Request $request,
        FeatureToggleRepository $featureToggleRepository
    ) {
        $this->validate($request, [
            'started_at'           => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at'             => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'channel_code'         => ['nullable', 'array'],
            'status'               => ['nullable', 'array'],
            'provider_device_name' => ['nullable', 'string'],
            'with_stats'           => ['nullable', 'boolean'],
        ]);

        DateRangeValidator::parse($request)
            ->validateMonths(2)
            ->validateDaysFromFeatureToggle($featureToggleRepository)
            ->validateDays(31);

        $userUpdate = [
            'last_activity_at' => now(),
            'ready_for_matching' => 1
        ];
        auth()->user()->update($userUpdate);

        $transactions = Transaction::where('type', Transaction::TYPE_PAUFEN_TRANSACTION);

        if (!is_null($request->only_self)) {
            $transactions->where('from_id', auth()->user()->id);
        } else {
            $transactions->whereIn('from_id', auth()->user()->getDescendantsId());
        }

        $transactions->latest()
            ->with('from', 'to', 'channel', 'transactionFees', 'certificateFiles', 'fromChannelAccount');

        $transactions->when($request->started_at, function ($builder, $startedAt) {
            $builder->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
        });

        $transactions->when($request->ended_at, function ($builder, $endedAt) {
            $builder->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
        });

        $transactions->when(!is_null($request->provider_device_name), function ($builder) use ($request) {
            $builder->where('from_device_name', 'like', "%{$request->provider_device_name}%");
        });

        $transactions->when(!is_null($request->provider_channel_account_hash_id), function ($builder) use ($request) {
            $builder->whereIn('from_channel_account_hash_id', $request->provider_channel_account_hash_id);
        });

        $transactions->when($request->descendant_provider_username_or_name, function ($builder, $descendantProviderUsernameOrName) {
            $builder->whereIn('from_id', function ($query) use ($descendantProviderUsernameOrName) {
                $query->select('id')
                    ->from('users')
                    ->orWhereIn('username', $descendantProviderUsernameOrName);
            });
        });

        $transactions->when(
            $request->system_order_number,
            function ($builder, $systemOrderNumber) {
                abort_if(
                    !$this->usingSystemOrderNumber($systemOrderNumber),
                    Response::HTTP_BAD_REQUEST,
                    __('common.Invalid format of system order number')
                );

                $builder->ofSystemOrderNumber($systemOrderNumber);
            }
        );

        $transactions->when(
            $request->channel_code,
            function ($builder, $channelCode) {
                $builder->whereIn('channel_code', $channelCode);
            }
        );

        $transactions->when(
            $request->amount,
            function ($builder, $amount) {
                $builder->where('amount', $amount);
            }
        );

        $transactions->when(
            $request->status,
            function ($builder, $status) {
                $builder->whereIn('status', $status);
            }
        );

        $allTransactions = $transactions->get();
        $userId = auth()->user()->getKey();
        $transactionFeeStats = optional();

        if ($request->boolean('with_stats')) {
            $totalAmount = $allTransactions->sum('floating_amount');

            $transactionFeeStats = TransactionFee::whereIn('transaction_id', $allTransactions->pluck('id'))
                ->where(function (Builder $transactionFees) {
                    if (auth()->user()->isRoot()) {
                        $transactionFees->where(function (Builder $transactionFees) {
                            $transactionFees->where('user_id', auth()->user()->getKey())
                                ->whereIn('account_mode', [User::ACCOUNT_MODE_GENERAL, User::ACCOUNT_MODE_DEPOSIT]);
                        })
                            ->orWhere(function (Builder $transactionFees) {
                                $transactionFees->whereHas('user', function (Builder $users) {
                                    $users->whereDescendantOf(auth()->user());
                                })
                                    ->where('account_mode', User::ACCOUNT_MODE_DEPOSIT);
                            });
                    } else {
                        $transactionFees->where('user_id', auth()->user()->getKey())
                            ->where('account_mode', User::ACCOUNT_MODE_GENERAL);
                    }
                })
                ->first([DB::raw('SUM(actual_profit) AS total_profit')]);
    }

        return TransactionCollection::make($transactions->paginate(20))
            ->additional([
                'meta' => [
                    'has_new_transaction' => Cache::pull("users_{$userId}_new_transaction", false),
                    'total_amount'        => AmountDisplayTransformer::transform($totalAmount ?? '0.00'),
                    'total_profit'        => AmountDisplayTransformer::transform($transactionFeeStats->total_profit ?? '0.00'),
                    'channel_note_enable' => Channel::where('note_enable', 1)->first() ? true : false
                ]
            ]);
    }

    private function usingSystemOrderNumber($orderNumberOrSystemOrderNumber)
    {
        return Str::startsWith($orderNumberOrSystemOrderNumber, [config('transaction.system_order_number_prefix')]);
    }

    public function show(Transaction $transaction)
    {
        abort_if(!$transaction->from->is(auth()->user()), Response::HTTP_NOT_FOUND);

        return \App\Http\Resources\Provider\Transaction::make($transaction->load('from', 'to', 'transactionFees.user', 'channel', 'certificateFiles'));
    }

    public function update(Request $request, Transaction $transaction, TransactionUtil $transactionUtil)
    {
        $user = auth()->user();
        $isUplineUser = $transaction->from->isDescendantOf($user);

        abort_if(!$user->canControl($transaction->from), Response::HTTP_NOT_FOUND);

        abort_if(
            $request->certificate
                && !in_array($transaction->status, [Transaction::STATUS_PAYING_TIMED_OUT]),
            Response::HTTP_BAD_REQUEST,
            '目前状态无法修改电子回单'
        );

        $this->validate($request, [
            'status' => ['int', Rule::in(Transaction::STATUS_MANUAL_SUCCESS)],
            'locked' => ['boolean'],
        ]);

        $transactionUtil->supportLockingLogics($transaction, $request, null, $isUplineUser);

        $this->certificateService->updateCertificate($request, $transaction);

        if (in_array($request->status, [Transaction::STATUS_MANUAL_SUCCESS])) {
            abort_if(
                $transaction->status === Transaction::STATUS_PAYING_TIMED_OUT,
                Response::HTTP_BAD_REQUEST,
                '订单已超时，请联络客服补单'
            );

            abort_if(
                $transaction->status !== Transaction::STATUS_PAYING,
                Response::HTTP_BAD_REQUEST,
                '只有支付中的订单可以补单'
            );

            abort_if(
                $transaction->locked && !$isUplineUser,
                Response::HTTP_BAD_REQUEST,
                '订单已锁定，请联系客服'
            );

            $transaction = $transactionUtil->markAsSuccess(
                $transaction,
                auth()->user(),
                false,
                $transaction->status === Transaction::STATUS_PAYING_TIMED_OUT
            );
        }

        return \App\Http\Resources\Provider\Transaction::make($transaction->load('from', 'to', 'transactionFees.user', 'channel', 'certificateFiles'));
    }

    public function updatePassword(Request $request, Transaction $transaction)
    {
        abort_if(
            $transaction->channel_code != Channel::CODE_RE_ALIPAY,
            Response::HTTP_BAD_REQUEST,
            '无法送出，请联系客服'
        );

        if (isset($transaction->to_channel_account['red_envelope_password'])) {
            return redirect($request->full_url);
        }

        $this->validate($request, [
            'red_envelope_password' => ['required']
        ]);

        $toChannelAccount = $transaction->to_channel_account;
        $toChannelAccount['red_envelope_password'] = $request->red_envelope_password;
        $toChannelAccount['recorded_at'] = now()->toIso8601String();
        $transaction->update(['to_channel_account' => $toChannelAccount]);

        return redirect($request->full_url);
    }

    public function updateQRcode(Request $request, Transaction $transaction)
    {
        abort_if(
            $transaction->channel_code != Channel::CODE_RE_QQ,
            Response::HTTP_BAD_REQUEST,
            '无法送出，请联系客服'
        );

        $this->validate($request, [
            'qr_code_img'       => 'required|file'
        ]);

        $file = $request->file('qr_code_img');

        $qrCodeFilePath = "transactions/{$transaction->id}/" . Str::random(32);

        $path = Storage::disk('user-channel-accounts-qr-code')->putFile($qrCodeFilePath, $file);

        $toChannelAccount = $transaction->to_channel_account;
        $toChannelAccount['re_qq_qrcode_path'] = $path;
        $toChannelAccount['recorded_at'] = now()->toIso8601String();
        $transaction->update(['to_channel_account' => $toChannelAccount]);

        return redirect($request->full_url);
    }

    public function bugReport(Request $request, Transaction $transaction)
    {
        $this->validate($request, [
            'bug_report'       => 'required'
        ]);

        $bug = $request->other ? $request->other : $request->bug_report;
        TransactionNote::create([
            'transaction_id'    => $transaction->id,
            'user_id'   => 0,
            'note'      => $bug,
        ]);
        $transaction->update(['bug_report' => $bug]);

        return redirect($request->full_url);
    }

    public function certificatesPresignedUrl(Transaction $transaction)
    {
        abort_if(
            !in_array($transaction->status, [Transaction::STATUS_PAYING_TIMED_OUT]),
            Response::HTTP_BAD_REQUEST,
            '目前状态无法修改电子回单'
        );

        return $this->certificateService->createPresignedUrl();
    }

}

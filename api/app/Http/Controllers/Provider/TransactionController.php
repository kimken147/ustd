<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\TransactionCollection;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\User;
use App\Models\Channel;
use App\Models\TransactionCertificateFile;
use App\Utils\AmountDisplayTransformer;
use App\Utils\TransactionUtil;
use App\Models\FeatureToggle;
use App\Models\TransactionNote;
use App\Repository\FeatureToggleRepository;
use DateTimeInterface;
use AWS;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class TransactionController extends Controller
{

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

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            $featureToggleRepository->enabled(FeatureToggle::VISIABLE_DAYS_OF_PROVIDER_TRANSACTIONS) &&
                now()->diffInDays($startedAt) > $featureToggleRepository->valueOf(FeatureToggle::VISIABLE_DAYS_OF_PROVIDER_TRANSACTIONS, 30),
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            !$startedAt
                || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            '时间区间最多一次筛选一个月，请重新调整时间'
        );

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

        $this->updateCertificateIfPresent($request, $transaction);

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

        $userId = auth()->user()->getAuthIdentifier();

        return Redis::funnel("funnel-create-certificate-presigned-url-$userId")->limit(1)->then(function () use ($userId) {
            $path = Str::random(40);
            $retryCount = 0;

            while (Storage::disk('transaction-certificate-files')->has($path) && $retryCount <= 10) {
                $path = Str::random(40);
                $retryCount += 1;
            }

            abort_if(
                Storage::disk('transaction-certificate-files')->has($path),
                Response::HTTP_BAD_REQUEST,
                '系统繁忙，请重试'
            );

            // 第二版電子回單使用
            $paths = collect(Cache::pull("certificate-paths-$userId") ?? []);

            $paths->push($path);

            Cache::put("certificate-paths-$userId", $paths, now()->addHour());

            // 暫時維持原樣以確保新舊程式重疊時不會有問題
            Cache::put("certificate-path-owner-$path", auth()->user()->getKey(), now()->addHour());

            $s3 = AWS::createClient('s3');

            $cmd = $s3->getCommand('PutObject', [
                'Bucket' => config('filesystems.disks.transaction-certificate-files.bucket'),
                'Key'    => $path,
            ]);

            $uri = (string) $s3->createPresignedRequest($cmd, '+1 hour')->getUri();

            return response()->json([
                'certificate_file_path'     => $path,
                'certificate_presigned_url' => $uri,
            ]);
        }, function () {
            abort(Response::HTTP_BAD_REQUEST, '请稍候重试');
        });
    }

    private function updateCertificateIfPresent(Request $request, Transaction $transaction)
    {
        // 第一版
        if ($path = $request->input('certificate_file_path')) {
            abort_if(
                auth()->user()->getKey() != Cache::pull("certificate-path-owner-$path"),
                Response::HTTP_BAD_REQUEST,
                '档案名称错误'
            );

            $transaction->update(['certificate_file_path' => $path]);
        }

        $requestedPaths = collect($request->input('certificate_file_paths', []))->unique();

        // 第二版
        if ($requestedPaths->isNotEmpty()) {
            $userId = auth()->user()->getAuthIdentifier();
            $existingPaths = $transaction->certificateFiles->pluck('path');
            $paths = collect(Cache::pull("certificate-paths-$userId") ?? [])->merge($existingPaths);

            abort_if(
                $requestedPaths->intersect($paths)->count() !== $requestedPaths->count(),
                Response::HTTP_BAD_REQUEST,
                '档案名称错误'
            );

            DB::transaction(function () use ($transaction, $requestedPaths) {
                if ($transaction->certificate_file_path) {
                    $transaction->update(['certificate_file_path' => null]);
                }

                $transaction->certificateFiles()->delete();
                $transaction->certificateFiles()->createMany($requestedPaths->map(function ($path) {
                    return compact('path');
                }));
            });

            $transaction->load('certificateFiles');
        }

        return $transaction;
    }
}

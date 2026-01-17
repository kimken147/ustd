<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\Withdraw;
use App\Http\Resources\Provider\WithdrawCollection;
use App\Models\BankCard;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\BannedRealname;
use App\Repository\FeatureToggleRepository;
use App\Utils\AmountDisplayTransformer;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\FloatUtil;
use App\Utils\TransactionFactory;
use App\Utils\WalletUtil;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class WithdrawController extends Controller
{

    public function index(
        Request $request,
        FeatureToggleRepository $featureToggleRepository
    ) {
        $this->validate($request, [
            'started_at' => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'ended_at'   => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'status'     => ['nullable', 'array'],
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

        $withdraws = Transaction::where('type', Transaction::TYPE_NORMAL_WITHDRAW);

        if (!is_null($request->only_self)) {
            $withdraws->where('from_id', auth()->user()->id);
        } else {
            $withdraws->whereIn('from_id', auth()->user()->getDescendantsId());
        }

        $withdraws->latest()
            ->with('from', 'transactionFees.user');

        $withdraws->when($request->started_at, function ($builder, $startedAt) {
            $builder->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
        });

        $withdraws->when($request->ended_at, function ($builder, $endedAt) {
            $builder->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
        });

        $withdraws->when($request->has('bank_card_q'), function (Builder $withdraws) use ($request) {
            $withdraws->where(function (Builder $withdraws) use ($request) {
                $bankCardQ = $request->bank_card_q;

                $withdraws->where('from_channel_account->bank_card_holder_name', 'like', "%$bankCardQ%")
                    ->orWhere('from_channel_account->bank_card_number', $bankCardQ)
                    ->orWhere('from_channel_account->bank_name', 'like', "%$bankCardQ%");
            });
        });

        $withdraws->when($request->descendant_merchent_username_or_name, function ($builder, $descendantMerchentUsernameOrName) {
            $builder->whereIn('from_id', function ($query) use ($descendantMerchentUsernameOrName) {
                $query->select('id')
                    ->from('users')
                    ->where('name', 'like', "%$descendantMerchentUsernameOrName%")
                    ->orWhere('username', $descendantMerchentUsernameOrName);
            });
        });

        $withdraws->when(
            $request->system_order_number,
            function ($builder, $systemOrderNumber) {
                abort_if(
                    !$this->usingSystemOrderNumber($systemOrderNumber),
                    Response::HTTP_BAD_REQUEST,
                    __('common.Invalid format of system order number')
                );

                $builder->where("system_order_number", $systemOrderNumber);
            }
        );

        $withdraws->when(
            $request->status,
            function ($builder, $status) {
                $builder->whereIn('status', $status);
            }
        );

        $stats = (clone $withdraws)->first(
            [
                DB::raw(
                    'SUM(amount) AS total_amount'
                ),
            ]
        );

        return WithdrawCollection::make($withdraws->paginate(20))
            ->additional([
                'meta' => [
                    'total_amount' => AmountDisplayTransformer::transform($stats->total_amount ?? '0.00'),
                ]
            ]);
    }

    private function usingSystemOrderNumber($orderNumberOrSystemOrderNumber)
    {
        return Str::startsWith($orderNumberOrSystemOrderNumber, config('transaction.system_order_number_prefix'));
    }

    public function show(Transaction $withdraw)
    {
        return Withdraw::make($withdraw->load('from', 'transactionFees.user'));
    }

    public function store(
        Request $request,
        BCMathUtil $bcMath,
        WalletUtil $wallet,
        TransactionFactory $transactionFactory,
        BankCardTransferObject $bankCardTransferObject,
        FeatureToggleRepository $featureToggleRepository,
        FloatUtil $floatUtil
    ) {
        abort_if(
            $request->type == 'balance' &&
            !auth()->user()->withdraw_enable,
            Response::HTTP_BAD_REQUEST,
            __('user.Withdraw disabled')
        );

        abort_if(
            $request->type == 'profit' &&
            !auth()->user()->withdraw_profit_enable,
            Response::HTTP_BAD_REQUEST,
            __('user.Withdraw disabled')
        );

        $this->validate($request, [
            'bank_card_id' => 'required',
            'amount'       => 'required|numeric|min:1',
            'type'         => 'required|in:balance,profit'
        ]);

        abort_if(
            $featureToggleRepository->enabled(FeatureToggle::NO_FLOAT_IN_WITHDRAWS)
            && $floatUtil->numberHasFloat($request->input('amount')),
            Response::HTTP_BAD_REQUEST,
            __('common.Decimal amount not allowed')
        );

        if (auth()->user()->google2fa_enable) {
            $this->validate($request, [
                config('google2fa.otp_input') => 'required|string',
            ]);

            /** @var Authenticator $authenticator */
            $authenticator = app(Authenticator::class)->bootStateless($request);

            abort_if(
                !$authenticator->isAuthenticated(),
                Response::HTTP_BAD_REQUEST,
                __('google2fa.Invalid OTP')
            );
        }

        $bankCard = BankCard::where('user_id', auth()->user()->getKey())
            ->find($request->bank_card_id);

        abort_if(
            !$bankCard,
            Response::HTTP_BAD_REQUEST,
            __('bank-card.Not owner')
        );

        abort_if(
            $bankCard->status !== BankCard::STATUS_REVIEW_PASSED,
            Response::HTTP_BAD_REQUEST,
            __('bank-card.Not reviewing passed')
        );

        abort_if(
            BannedRealname::where(['realname' => $bankCard->bank_card_holder_name, 'type' => BannedRealname::TYPE_WITHDRAW])->exists(),
            Response::HTTP_BAD_REQUEST,
            __('common.Card holder access forbidden')
        );

        abort_if(
            $request->type == 'balance' &&
            $bcMath->gtZero(auth()->user()->wallet->withdraw_min_amount ?? 0)
            && $bcMath->lt($request->input('amount'), auth()->user()->wallet->withdraw_min_amount),
            Response::HTTP_BAD_REQUEST,
            __('withdraw.Balance withdrawal amount is lower')
        );

        abort_if(
            $request->type == 'balance' &&
            $bcMath->gtZero(auth()->user()->wallet->withdraw_max_amount ?? 0)
            && $bcMath->gt($request->input('amount'), auth()->user()->wallet->withdraw_max_amount),
            Response::HTTP_BAD_REQUEST,
            __('withdraw.Balance withdrawal amount is higher')
        );

        abort_if(
            $request->type == 'profit' &&
            $bcMath->gtZero(auth()->user()->wallet->withdraw_profit_min_amount ?? 0)
            && $bcMath->lt($request->input('amount'), auth()->user()->wallet->withdraw_profit_min_amount),
            Response::HTTP_BAD_REQUEST,
            __('withdraw.Bonus withdrawal amount is lower')
        );

        abort_if(
            $request->type == 'profit' &&
            $bcMath->gtZero(auth()->user()->wallet->withdraw_profit_max_amount ?? 0)
            && $bcMath->gt($request->input('amount'), auth()->user()->wallet->withdraw_profit_max_amount),
            Response::HTTP_BAD_REQUEST,
            __('withdraw.Bonus withdrawal amount is higher')
        );

        $totalCost = $bcMath->add($bcMath->abs($request->amount),
            $request->type == 'balance' ? auth()->user()->wallet->withdraw_fee : auth()->user()->wallet->withdraw_profit_fee
        );

        abort_if(
            $request->type == 'balance' &&
            $bcMath->lt(auth()->user()->wallet->available_balance, $totalCost),
            Response::HTTP_BAD_REQUEST,
            __('wallet.InsufficientAvailableBalance')
        );

        abort_if(
            $request->type == 'profit' &&
            $bcMath->lt(auth()->user()->wallet->profit, $totalCost),
            Response::HTTP_BAD_REQUEST,
            __('wallet.InsufficientAvailableProfit')
        );

        $withdraw = DB::transaction(function () use (
            $bankCard,
            $request,
            $wallet,
            $totalCost,
            $transactionFactory,
            $bankCardTransferObject
        ) {
            /** @var Transaction $transaction */
            $transaction = $transactionFactory
                ->bankCard($bankCardTransferObject->model($bankCard))
                ->amount($request->amount);

            if ($request->type == 'profit') {
                $transaction->subType(TRANSACTION::SUB_TYPE_WITHDRAW_PROFIT);
            }

            $transaction = $transaction->normalWithdrawFrom(auth()->user(), false, null, $request->type);

            $wallet->withdraw(auth()->user()->wallet, $totalCost, $transaction->order_number, $transactionType='withdraw', $request->type);

            return $transaction;
        });

        Cache::put('admin_withdraws_added_at', now(), now()->addSeconds(60));

        return Withdraw::make($withdraw->load('from', 'transactionFees.user'));
    }
}

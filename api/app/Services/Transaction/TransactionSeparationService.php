<?php

namespace App\Services\Transaction;

use App\Exceptions\ChildWithdrawCannotSeparateException;
use App\Exceptions\InvalidChildWithdrawAmountException;
use App\Exceptions\InvalidMaxSeparateWithdrawCountException;
use App\Exceptions\InvalidMinSeparateWithdrawCountException;
use App\Exceptions\OnlyMerchantCanSeparateWithdrawException;
use App\Exceptions\SeparateWithdrawTotalAmountNotMatchException;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Utils\BCMathUtil;
use App\Utils\BankCardTransferObject;
use App\Utils\TransactionFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionSeparationService
{
    public function __construct(
        private BCMathUtil $bcMath,
        private TransactionFactory $transactionFactory,
        private BankCardTransferObject $bankCardTransferObject
    ) {}

    /**
     * 拆單
     *
     * @param callable $markAsNormalWithdraw fn(Transaction, ?string, bool, bool) => Transaction
     */
    public function separateWithdraw(
        Transaction $withdraw,
        Collection $childWithdraws,
        bool $shouldLock,
        callable $markAsNormalWithdraw
    ): Transaction {
        abort_if(
            $withdraw->type === Transaction::TYPE_PAUFEN_WITHDRAW && $withdraw->to_id,
            Response::HTTP_BAD_REQUEST,
            '该笔跑分提现码商已抢单，请使用「充值管理」确认订单资讯'
        );

        throw_if(
            $withdraw->isChild(),
            new ChildWithdrawCannotSeparateException()
        );

        throw_if(
            $withdraw->from->role !== User::ROLE_MERCHANT,
            new OnlyMerchantCanSeparateWithdrawException()
        );

        throw_if(
            $childWithdraws->count() < 2,
            new InvalidMinSeparateWithdrawCountException()
        );

        throw_if(
            $childWithdraws->count() > 10,
            new InvalidMaxSeparateWithdrawCountException()
        );

        foreach ($childWithdraws as $childWithdraw) {
            throw_if(
                !$this->bcMath->gtZero($childWithdraw['amount']),
                new InvalidChildWithdrawAmountException()
            );
        }

        $totalChildWithdrawAmount = $this->bcMath->sum($childWithdraws->pluck('amount')->toArray());

        throw_if(
            $this->bcMath->notEqual($withdraw->amount, $totalChildWithdrawAmount),
            new SeparateWithdrawTotalAmountNotMatchException()
        );

        return DB::transaction(function () use ($withdraw, $childWithdraws, $shouldLock, $markAsNormalWithdraw) {
            /** @var Transaction $withdraw */
            $withdraw = Transaction::lockForUpdate()->findOrFail($withdraw->getKey());

            abort_if(
                $shouldLock
                    && !$withdraw->locked,
                Response::HTTP_BAD_REQUEST,
                __('transaction.You have to lock before doing this')
            );

            abort_if(
                $shouldLock
                    && $withdraw->locked
                    && !$withdraw->lockedBy->is(auth()->user()->realUser()),
                Response::HTTP_BAD_REQUEST,
                __('transaction.Already been locked, you are not allowing to do status update')
            );

            abort_if(
                $withdraw->children()->exists(),
                Response::HTTP_BAD_REQUEST,
                '订单已拆单'
            );

            abort_if(
                !in_array(
                    $withdraw->status,
                    [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]
                ),
                Response::HTTP_BAD_REQUEST,
                '目前订单状态无法拆单'
            );

            $withdraw = $markAsNormalWithdraw($withdraw, null, $shouldLock, true);

            foreach ($childWithdraws as $childWithdraw) {
                $bankCard = $this->bankCardTransferObject->plain(
                    $withdraw->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_NAME],
                    $withdraw->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER],
                    $withdraw->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME],
                    $withdraw->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_PROVINCE] ?? '',
                    $withdraw->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_CITY] ?? ''
                );

                $params = new \App\DTOs\TransactionParams(
                    amount: data_get($childWithdraw, 'amount'),
                    bankCard: $bankCard,
                    clientIpv4: $withdraw->client_ipv4,
                    parent: $withdraw,
                    subType: $withdraw->sub_type,
                );

                switch (data_get($childWithdraw, 'type')) {
                    case Transaction::TYPE_PAUFEN_WITHDRAW:
                        /** @var Transaction $childWithdrawModel */
                        $childWithdrawModel = $this->transactionFactory->paufenWithdrawFrom(
                            $params,
                            $withdraw->from,
                            false,
                            $withdraw
                        );

                        $providerId = data_get($childWithdraw, 'to_id');

                        // 有指定碼商
                        if ($providerId) {
                            $provider = User::find($providerId);

                            abort_if(!$provider, Response::HTTP_BAD_REQUEST, '查无使用者');
                            abort_if($provider->role !== User::ROLE_PROVIDER, Response::HTTP_BAD_REQUEST, '仅能指定码商');

                            $this->transactionFactory->paufenDepositTo($provider, $childWithdrawModel, $withdraw);
                        }

                        $channelAccountId = data_get($childWithdraw, 'to_channel_account_id');
                        // 有指定出款帳號
                        if ($channelAccountId) {
                            $account = UserChannelAccount::find($channelAccountId);

                            abort_if(!$provider, Response::HTTP_BAD_REQUEST, '查无出款帐号');

                            $this->transactionFactory->paufenDepositToAccount($account, $childWithdrawModel, $withdraw);
                        }

                        break;
                    case Transaction::TYPE_NORMAL_WITHDRAW:
                        $this->transactionFactory->normalWithdrawFrom($params, $withdraw->from, false, $withdraw);
                        break;
                    default:
                        abort(Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            return $withdraw->refresh();
        });
    }

    /**
     * 建立子跑分交易
     */
    public function createChildPaufenTransaction(
        Transaction $transaction,
        $amount,
        ?User $operator = null,
        $autoSuccess = false
    ): Transaction {
        /** @var Transaction $childTransaction */
        $childTransaction = Transaction::create([
            'parent_id'                    => $transaction->getKey(),
            'from_id'                      => $transaction->from_id,
            'from_wallet_id'               => $transaction->from_wallet_id,
            'to_id'                        => $transaction->to_id,
            'to_wallet_id'                 => $transaction->to_wallet_id,
            'locked_by_id'                 => $transaction->locked_by_id,
            'thirdchannel_id'              => $transaction->thirdchannel_id,
            'from_channel_account_id'      => $transaction->from_channel_account_id,
            'operator_id'                  => optional($operator)->getKey(),
            'client_ipv4'                  => $transaction->client_ipv4,
            'type'                         => $transaction->type,
            'status'                       => $autoSuccess ? Transaction::STATUS_SUCCESS : Transaction::STATUS_MANUAL_SUCCESS,
            'notify_status'                => Transaction::NOTIFY_STATUS_NONE,
            'from_account_mode'            => $transaction->from_account_mode,
            'to_account_mode'              => $transaction->to_account_mode,
            'from_channel_account'         => $transaction->from_channel_account,
            'to_channel_account'           => $transaction->to_channel_account,
            'amount'                       => $amount,
            'floating_amount'              => $amount,
            'actual_amount'                => $amount,
            'channel_code'                 => $transaction->channel_code,
            'from_channel_account_hash_id' => $transaction->from_channel_account_hash_id,
            'order_number'                 => null,
            'note'                         => $transaction->note,
            'notify_url'                   => null,
            'from_device_name'             => $transaction->from_device_name,
            'certificate_file_path'        => $transaction->certificate_file_path,
            'notified_at'                  => null,
            'matched_at'                   => $transaction->matched_at,
            'confirmed_at'                 => $now = now(),
            'locked_at'                    => $transaction->locked_at,
            'operated_at'                  => $now
        ]);

        $partialPercent = $this->bcMath->div($amount, $transaction->amount);

        $transaction->transactionFees->each(function (TransactionFee $transactionFee) use ($childTransaction, $partialPercent) {

            $childTransaction->transactionFees()->create([
                'user_id'         => $transactionFee->user_id,
                'account_mode'    => $transactionFee->account_mode,
                'profit'          => $profit = $this->bcMath->mul($transactionFee->profit, $partialPercent),
                'actual_profit'   => $profit,
                'fee'             => $fee = $this->bcMath->mul($transactionFee->fee, $partialPercent),
                'actual_fee'      => $fee,
                'thirdchannel_id' => $transactionFee->thirdchannel_id
            ]);
        });

        // 如果有出款帳號，成功話 出款帳號扣除額度
        if ($childTransaction->to_channel_account_id) {
            $account = $childTransaction->toChannelAccount;
            if ($account) {
                $account->updateBalanceByTransaction($childTransaction);
            }
        }
        // 如果有收款帳號，成功話 收款帳號累加額度
        if ($childTransaction->from_channel_account_id) {
            $account = $childTransaction->fromChannelAccount;
            if ($account) {
                $account->updateBalanceByTransaction($childTransaction);
            }
        }

        return $childTransaction;
    }
}

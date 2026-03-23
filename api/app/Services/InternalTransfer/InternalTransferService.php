<?php

namespace App\Services\InternalTransfer;

use App\DTOs\TransactionParams;
use App\Jobs\ProcessNativeTransfer;
use App\Jobs\ProcessUsdtWithdraw;
use App\Models\Transaction;
use App\Models\UserChannelAccount;
use App\Services\Transaction\TransactionStatusRules;
use App\Utils\BCMathUtil;
use App\Utils\BankCardTransferObject;
use App\Utils\TransactionFactory;
use App\Utils\UserChannelAccountUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InternalTransferService
{
    public function __construct(
        private readonly BCMathUtil $bcMath,
        private readonly TransactionFactory $transactionFactory,
        private readonly UserChannelAccountUtil $userChannelAccountUtil,
    ) {}

    /**
     * Create an internal transfer transaction.
     */
    public function execute(Request $request): Transaction
    {
        $account = UserChannelAccount::findOrFail($request->input('account_id'));
        $currency = $request->input('currency', Transaction::CURRENCY_USDT);

        $this->validateAccountAvailability($account);

        $transaction = $this->createTransaction($request, $account, $currency);

        abort_if(!$transaction, Response::HTTP_BAD_REQUEST, __('common.Create transfer failed'));

        $this->updateAccountTotal($account, $transaction);

        $this->dispatchTransferJob($transaction, $account, $currency);

        return $transaction;
    }

    /**
     * Validate that the account has no pending transfers.
     */
    private function validateAccountAvailability(UserChannelAccount $account): void
    {
        $exists = Transaction::where('to_channel_account_id', $account->id)
            ->where('status', Transaction::STATUS_PAYING)
            ->where('created_at', '>', now()->subDay())
            ->exists();

        abort_if(
            $exists,
            Response::HTTP_FORBIDDEN,
            __('common.Account is processing transfer, please try later', ['account' => $account->account])
        );
    }

    /**
     * Create the transaction using TransactionFactory.
     */
    private function createTransaction(Request $request, UserChannelAccount $account, string $currency = 'USDT'): ?Transaction
    {
        $bankCard = app(BankCardTransferObject::class)->plain(
            $request->input('bank_name'),
            $request->input('bank_card_number', ''),
            $request->input('bank_card_holder_name'),
            $request->input('bank_province', ''),
            $request->input('bank_city', ''),
        );

        $orderNumber = $request->input(
            'order_id',
            chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . chr(mt_rand(65, 90)) . date('YmdHis') . rand(100, 999)
        );

        $params = new TransactionParams(
            amount: $request->input('amount'),
            bankCard: $bankCard,
            note: $request->input('note'),
            orderNumber: $orderNumber,
        );

        return $this->transactionFactory->internalTransferFrom($params, $account, $currency);
    }

    /**
     * Update account totals after transfer creation.
     * - 出款帳號（to_channel_account）：增加已出額度
     * - 接收帳號的額度和餘額在 ConfirmUsdtWithdraw 成功時更新
     */
    private function updateAccountTotal(UserChannelAccount $account, Transaction $transaction): void
    {
        // 出款帳號：更新出款限額
        $this->userChannelAccountUtil->updateTotal($account->id, $transaction->amount, true);
        $this->userChannelAccountUtil->updatePaymentCount($account->id, 1, true);
    }

    /**
     * 根據 from_channel_account 的 bank_card_number 找到接收帳號
     */
    private function getCompanyAccount(Transaction $transaction): ?UserChannelAccount
    {
        if (!in_array($transaction->type, [Transaction::TYPE_INTERNAL_TRANSFER, Transaction::TYPE_NATIVE_TRANSFER])) {
            return null;
        }
        if (!$transaction->from_channel_account) {
            return null;
        }

        $address = data_get($transaction->from_channel_account, 'bank_card_number');
        if (!$address) {
            return null;
        }

        return UserChannelAccount::where('account', $address)->first();
    }

    /**
     * Dispatch the appropriate transfer job based on currency.
     */
    private function dispatchTransferJob(Transaction $transaction, UserChannelAccount $account, string $currency): void
    {
        if (!$transaction->to_channel_account_id) {
            return;
        }

        if ($currency === Transaction::CURRENCY_USDT) {
            if (TransactionStatusRules::shouldDispatchUsdtWithdraw(
                false,
                $account->channel_code,
                $transaction->thirdchannel_id,
                $transaction->to_channel_account_id
            )) {
                ProcessUsdtWithdraw::dispatch($transaction->id);
            }
        } else {
            ProcessNativeTransfer::dispatch($transaction->id);
        }
    }
}

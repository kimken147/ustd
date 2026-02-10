<?php

namespace App\Services\InternalTransfer;

use App\Models\Transaction;
use App\Models\UserChannelAccount;
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

        $this->validateAccountAvailability($account);

        $transaction = $this->createTransaction($request, $account);

        abort_if(!$transaction, Response::HTTP_BAD_REQUEST, __('common.Create transfer failed'));

        $this->updateAccountTotal($account, $transaction);

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
    private function createTransaction(Request $request, UserChannelAccount $account): ?Transaction
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

        return $this->transactionFactory->fresh()
            ->bankCard($bankCard)
            ->orderNumber($orderNumber)
            ->amount($request->input('amount'))
            ->note($request->input('note'))
            ->internalTransferFrom($account);
    }

    /**
     * Update account totals after transfer creation.
     */
    private function updateAccountTotal(UserChannelAccount $account, Transaction $transaction): void
    {
        $this->userChannelAccountUtil->updateTotal(
            $account->id,
            $transaction->amount,
            true
        );
    }
}

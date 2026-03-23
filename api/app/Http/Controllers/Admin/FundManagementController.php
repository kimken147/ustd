<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\TransactionParams;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserChannelAccount as UserChannelAccountResource;
use App\Jobs\BatchTransferUsdt;
use App\Models\Transaction;
use App\Models\UserChannelAccount;
use App\Utils\BankCardTransferObject;
use App\Models\Channel;
use App\Utils\TransactionFactory;
use App\Utils\UserChannelAccountUtil;
use Illuminate\Http\Request;

class FundManagementController extends Controller
{
    /**
     * 列出所有 USDT 帳號及鏈上餘額
     * GET /fund-management/accounts
     */
    public function index(Request $request)
    {
        $accounts = UserChannelAccount::with('user', 'parentAccount')
            ->whereIn('channel_code', Channel::USDT_CODES)
            ->whereNull('deleted_at')
            ->orderByDesc('onchain_usdt_balance')
            ->get();

        return UserChannelAccountResource::collection($accounts);
    }

    /**
     * 批量轉帳：從多個來源帳號轉到一個目標帳號
     * POST /fund-management/batch-transfer
     */
    public function batchTransfer(Request $request, TransactionFactory $factory)
    {
        $validated = $request->validate([
            'source_account_ids'   => 'required|array|min:1',
            'source_account_ids.*' => 'integer|exists:user_channel_accounts,id',
            'target_account_id'    => 'required|integer|exists:user_channel_accounts,id',
        ]);

        $targetAccount = UserChannelAccount::findOrFail($validated['target_account_id']);

        if (!Channel::isUsdt($targetAccount->channel_code)) {
            abort(400, '目標帳號必須是 USDT 通道');
        }

        $sourceAccounts = UserChannelAccount::whereIn('id', $validated['source_account_ids'])
            ->whereIn('channel_code', Channel::USDT_CODES)
            ->get();

        foreach ($sourceAccounts as $source) {
            $encryptedKey = data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
            if (empty($encryptedKey)) {
                abort(400, "帳號 {$source->account} 缺少私鑰，無法轉帳");
            }
        }

        // 檢查來源帳號是否有正在進行的付款（代付、建立轉帳、批量轉帳）
        $busyAccountIds = Transaction::whereIn('to_channel_account_id', $sourceAccounts->pluck('id'))
            ->where('status', Transaction::STATUS_PAYING)
            ->where('created_at', '>=', now()->subDay())
            ->pluck('to_channel_account_id')
            ->unique()
            ->toArray();

        if (!empty($busyAccountIds)) {
            $busyNames = $sourceAccounts->whereIn('id', $busyAccountIds)
                ->map(fn ($a) => "{$a->account}")
                ->implode(', ');
            abort(400, "以下帳號正在進行其他付款，請稍後再試: {$busyNames}");
        }

        $dispatched = 0;

        foreach ($sourceAccounts as $source) {
            $chainNetwork = data_get($source->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');

            // from_channel_account = 接收方資訊（跟代付語意一致：from = 目標地址）
            $bankCard = app(BankCardTransferObject::class)->plain(
                $chainNetwork,                    // bank_name → 鏈網路
                $targetAccount->account,          // bank_card_number → 接收地址
                $targetAccount->user?->name ?? '', // bank_card_holder_name
                '',
                '',
            );

            $orderNumber = 'BT' . date('YmdHis') . rand(100, 999);

            $params = new TransactionParams(
                amount: $source->onchain_usdt_balance,
                bankCard: $bankCard,
                note: 'batch-transfer',
                orderNumber: $orderNumber,
            );

            // $account = 出款帳號（跟代付語意一致：to_channel_account_id = 出款帳號）
            $transaction = $factory->internalTransferFrom($params, $source);

            if (!$transaction) {
                continue;
            }

            // 出款方額度（建立時更新）
            $util = app(UserChannelAccountUtil::class);
            $util->updateTotal($source->id, $transaction->amount, true);
            $util->updatePaymentCount($source->id, 1, true);
            // 收款方額度在 ConfirmUsdtWithdraw 成功時更新

            BatchTransferUsdt::dispatch($transaction->id);
            $dispatched++;
        }

        return response()->json([
            'message' => "已排入 {$dispatched} 筆轉帳任務",
            'count'   => $dispatched,
        ]);
    }
}

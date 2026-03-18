<?php

namespace App\Http\Controllers\Admin;

use App\DTOs\TransactionParams;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserChannelAccount as UserChannelAccountResource;
use App\Jobs\BatchTransferUsdt;
use App\Models\Transaction;
use App\Models\UserChannelAccount;
use App\Utils\BankCardTransferObject;
use App\Utils\TransactionFactory;
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
            ->where('channel_code', 'USDT')
            ->where('onchain_usdt_balance', '>', 0)
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

        if ($targetAccount->channel_code !== 'USDT') {
            abort(400, '目標帳號必須是 USDT 通道');
        }

        $sourceAccounts = UserChannelAccount::whereIn('id', $validated['source_account_ids'])
            ->where('channel_code', 'USDT')
            ->get();

        foreach ($sourceAccounts as $source) {
            $encryptedKey = data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
            if (empty($encryptedKey)) {
                abort(400, "帳號 {$source->account} 缺少私鑰，無法轉帳");
            }
        }

        $dispatched = 0;

        foreach ($sourceAccounts as $source) {
            $chainNetwork = data_get($source->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');

            // 建立 BankCardTransferObject 作為 from_channel_account
            $bankCard = app(BankCardTransferObject::class)->plain(
                $chainNetwork,             // bank_name → 鏈網路
                $source->account,          // bank_card_number → 來源地址
                $source->user?->name ?? '', // bank_card_holder_name → 商戶名
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

            $transaction = $factory->internalTransferFrom($params, $targetAccount);

            if (!$transaction) {
                continue;
            }

            // 記錄來源帳號
            $transaction->update([
                'from_channel_account_id' => $source->id,
            ]);

            BatchTransferUsdt::dispatch($transaction->id);
            $dispatched++;
        }

        return response()->json([
            'message' => "已排入 {$dispatched} 筆轉帳任務",
            'count'   => $dispatched,
        ]);
    }
}

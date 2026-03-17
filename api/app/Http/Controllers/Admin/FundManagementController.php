<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserChannelAccount as UserChannelAccountResource;
use App\Jobs\BatchTransferUsdt;
use App\Models\UserChannelAccount;
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
    public function batchTransfer(Request $request)
    {
        $validated = $request->validate([
            'source_account_ids'   => 'required|array|min:1',
            'source_account_ids.*' => 'integer|exists:user_channel_accounts,id',
            'target_account_id'    => 'required|integer|exists:user_channel_accounts,id',
        ]);

        $targetAccount = UserChannelAccount::findOrFail($validated['target_account_id']);

        // 驗證目標帳號是 USDT
        if ($targetAccount->channel_code !== 'USDT') {
            abort(400, '目標帳號必須是 USDT 通道');
        }

        $sourceAccounts = UserChannelAccount::whereIn('id', $validated['source_account_ids'])
            ->where('channel_code', 'USDT')
            ->get();

        // 驗證所有來源帳號有私鑰
        foreach ($sourceAccounts as $source) {
            $encryptedKey = data_get($source->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
            if (empty($encryptedKey)) {
                abort(400, "帳號 {$source->account} 缺少私鑰，無法轉帳");
            }
        }

        // 逐筆 dispatch 轉帳 Job
        $dispatched = 0;
        foreach ($sourceAccounts as $source) {
            BatchTransferUsdt::dispatch(
                $source->id,
                $targetAccount->account,
                $source->onchain_usdt_balance,
            );
            $dispatched++;
        }

        return response()->json([
            'message' => "已排入 {$dispatched} 筆轉帳任務",
            'count'   => $dispatched,
        ]);
    }
}

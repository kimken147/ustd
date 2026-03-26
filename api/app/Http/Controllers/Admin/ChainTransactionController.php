<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChainTransactionResource;
use App\Models\ChainTransaction;
use App\Models\Permission;
use App\Services\Crypto\ChainTransactionMatchService;
use App\Services\Crypto\ChainTransactionSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChainTransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware([
            'permission:' . Permission::ADMIN_VIEW_CHAIN_TRANSACTION,
        ])->only(['index', 'show']);
        $this->middleware([
            'permission:' . Permission::ADMIN_UPDATE_CHAIN_TRANSACTION,
        ])->only(['match', 'ignore', 'restore', 'sync']);
    }

    /**
     * 列出鏈上交易，支援多種篩選條件
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ChainTransaction::with(['userChannelAccount', 'matchedTransaction'])
            ->orderByDesc('block_timestamp');

        if ($request->filled('match_status')) {
            $query->where('match_status', $request->input('match_status'));
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        if ($request->filled('token_type')) {
            $query->where('token_type', $request->input('token_type'));
        }

        if ($request->filled('user_channel_account_id')) {
            $query->where('user_channel_account_id', $request->input('user_channel_account_id'));
        }

        if ($request->filled('tx_hash')) {
            $query->where('tx_hash', $request->input('tx_hash'));
        }

        // 同時搜尋 from_address 和 to_address
        if ($request->filled('address')) {
            $address = $request->input('address');
            $query->where(function ($q) use ($address) {
                $q->where('from_address', $address)
                    ->orWhere('to_address', $address);
            });
        }

        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', $request->input('amount_min'));
        }

        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', $request->input('amount_max'));
        }

        if ($request->filled('start_date')) {
            $query->where('block_timestamp', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('block_timestamp', '<=', $request->input('end_date'));
        }

        $perPage = $request->input('per_page', 20);
        $data = $query->paginate($perPage);

        return ChainTransactionResource::collection($data);
    }

    /**
     * 查看單筆鏈上交易詳情
     */
    public function show(ChainTransaction $chainTransaction): ChainTransactionResource
    {
        $chainTransaction->load(['userChannelAccount', 'matchedTransaction', 'matchedByUser']);
        return new ChainTransactionResource($chainTransaction);
    }

    /**
     * 手動關聯鏈上交易與系統訂單
     */
    public function match(
        Request $request,
        ChainTransaction $chainTransaction,
        ChainTransactionMatchService $matchService,
    ) {
        $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
        ]);

        if ($chainTransaction->match_status === ChainTransaction::STATUS_MATCHED) {
            return response()->json(['message' => '此交易已經被關聯'], 422);
        }

        $matchService->manualMatch(
            $chainTransaction,
            $request->input('transaction_id'),
            auth()->id(),
        );

        return new ChainTransactionResource($chainTransaction->fresh(['userChannelAccount', 'matchedTransaction']));
    }

    /**
     * 忽略鏈上交易（標記為不需處理）
     */
    public function ignore(Request $request, ChainTransaction $chainTransaction)
    {
        if ($chainTransaction->match_status === ChainTransaction::STATUS_MATCHED) {
            return response()->json(['message' => '已匹配的交易不能忽略'], 422);
        }

        $chainTransaction->update([
            'match_status' => ChainTransaction::STATUS_IGNORED,
            'note' => $request->input('note', $chainTransaction->note),
        ]);

        return new ChainTransactionResource($chainTransaction->fresh());
    }

    /**
     * 恢復被忽略的鏈上交易，重新嘗試自動比對
     */
    public function restore(ChainTransaction $chainTransaction, ChainTransactionMatchService $matchService)
    {
        if ($chainTransaction->match_status !== ChainTransaction::STATUS_IGNORED) {
            return response()->json(['message' => '只能恢復被忽略的交易'], 422);
        }

        $chainTransaction->update([
            'match_status' => ChainTransaction::STATUS_PENDING,
            'matched_transaction_id' => null,
            'matched_at' => null,
            'matched_by' => null,
        ]);

        // 恢復後嘗試自動比對
        $matchService->matchTransaction($chainTransaction->fresh());

        return new ChainTransactionResource($chainTransaction->fresh());
    }

    /**
     * 手動觸發同步所有帳號的鏈上交易
     */
    public function sync(ChainTransactionSyncService $syncService)
    {
        $result = $syncService->syncAllAccounts();
        return response()->json([
            'message' => "同步完成：{$result['accounts']} 個帳號，新增 {$result['synced']} 筆交易",
            'data' => $result,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CashierController extends Controller
{
    /**
     * 第三方 API 欄位 → 內部收銀台欄位的對應表
     */
    private const FIELD_MAP = [
        'merchant_id'  => 'username',
        'channel'      => 'channel_code',
        'out_trade_no' => 'order_number',
        'callback_url' => 'notify_url',
        'payer_name'   => 'real_name',
        'redirect_url' => 'return_url',
        'network'      => 'bank_name',
    ];

    public function __invoke(Request $request, $id)
    {
        $transaction = Transaction::where('system_order_number', $id)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        abort_if(!$transaction, Response::HTTP_NOT_FOUND, '查无此订单');

        $query = json_decode($transaction->to_channel_account['query'] ?? '{}', true) ?? [];

        // 將第三方 API 欄位名稱轉換為內部收銀台欄位名稱
        $mapped = [];
        foreach ($query as $key => $value) {
            $mapped[self::FIELD_MAP[$key] ?? $key] = $value;
        }

        $request->request->add($mapped);

        return app()->call('\App\Http\Controllers\CreateTransactionController@__invoke', ['request' => $request]);
    }
}

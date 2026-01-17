<?php

namespace App\Http\Controllers\Exchange;

use App\Http\Controllers\Controller;
use App\Http\Resources\Exchange\TransactionCollection;
use App\Models\Channel;
use App\Models\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{

    public function index(Request $request)
    {
        auth()->user()->update([
            'last_activity_at' => now(),
        ]);

        $types = $request->filled('type') ? [$request->input('type')] : [
            Transaction::TYPE_PAUFEN_TRANSACTION, Transaction::TYPE_PAUFEN_WITHDRAW
        ];

        $transactions = Transaction::whereIn('type', $types)
            ->where(function (Builder $transactions) {
                $transactions->where('from_id', auth()->user()->getKey())
                    ->orWhere('to_id', auth()->user()->getKey());
            })
            ->latest();

        $startedAt = data_get((clone $transactions)->first(), 'created_at', now())->subDays(5);

        $transactions->where('created_at', '>=', $startedAt)->with('certificateFiles', 'fakeCryptoTransaction');

        $userId = auth()->user()->getKey();
        $alipayBankUserChannel = auth()->user()->userChannels()->whereHas('channelGroup',
            function (Builder $channelGroup) {
                $channelGroup->where('channel_code', Channel::CODE_ALIPAY_BANK);
            })->first();

        return TransactionCollection::make($transactions->paginate())
            ->additional([
                'meta' => [
                    'has_new_transaction' => Cache::pull("users_{$userId}_new_transaction", false),
                    'btc_cny'             => '120,000.00',
                    'eth_cny'             => '3,800.00',
                    'usdt_cny'            => '6.50',
                    'selling_fee'         => data_get($alipayBankUserChannel, 'fee_percent', '1.10').'%',
                ]
            ]);
    }

    public function update(Request $request, Transaction $transaction, TransactionUtil $transactionUtil)
    {
        abort_if(!$transaction->from->is(auth()->user()), Response::HTTP_NOT_FOUND);

        $this->validate($request, [
            'status' => ['int', Rule::in(Transaction::STATUS_MANUAL_SUCCESS)],
        ]);

        if (in_array($request->status, [Transaction::STATUS_MANUAL_SUCCESS])) {
            abort_if(
                $transaction->status === Transaction::STATUS_PAYING_TIMED_OUT,
                Response::HTTP_BAD_REQUEST,
                '订单已超时，请联络客服'
            );

            abort_if(
                $transaction->status !== Transaction::STATUS_PAYING,
                Response::HTTP_BAD_REQUEST,
                '订单状态已改变，请刷新后重试'
            );

            abort_if(
                $transaction->locked,
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

        return \App\Http\Resources\Exchange\Transaction::make($transaction);
    }
}

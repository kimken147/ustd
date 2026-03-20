<?php

namespace App\Http\Resources\ThirdParty;

use App\Models\Channel;
use App\Models\UserChannel;
use App\Models\TransactionFee;
use App\Models\Transaction;
use App\Models\User;
use App\Utils\SignatureCalculator;
use Illuminate\Http\Resources\Json\ResourceCollection;
use RuntimeException;

class TransactionListCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $collectionList = $this->collection;
        $list = [];

        foreach ($collectionList as $key => $item) {
            $tmp = [];
            $tmp = [
                'trade_no'            => $item->system_order_number,
                'out_trade_no'        => $item->order_number,
                'status'              => Transaction::toMerchantStatus($item->status),
                'amount'              => $item->amount,
                'fee'                 => $item->from ? ($item->transactionFees->filter($this->filteredByUser($item->from))->first()?->fee ?? "0.00") : "0.00",
                'merchant_id'         => $item->to->username ?? '',
                'note'                => $item->channel->note_enable ?? '',
                'callback_url'        => $item->notify_url,
                'created_at'          => $item->created_at->toIso8601String(),
                'confirmed_at'        => optional($item->confirmed_at)->toIso8601String() ?? '',
            ];
            $list[] = $tmp;
        }

        $data = [
            'list' => $list,
            'current_page' => $this->resource->currentPage(),
            'total_pages' => $this->resource->lastPage(),
            'per_page' => $this->resource->perPage(),
            'total' => $this->resource->total(),
        ];
        $user = $this->resource->first()?->to;

        // 生成簽名
        $data['sign'] = $user ? $this->generateSign($user, $data) : '';

        // 自定義返回結構
        return [
            'data' => $data,
            'message' => __("transaction.success"),
        ];
    }

    /**
     * Generate a sign for the given data.
     *
     * @param  mixed $user
     * @param  array $data
     * @return string
     */
    private function generateSign($user, array $data)
    {
        throw_if(
            empty($user->secret_key),
            new RuntimeException()
        );

        return SignatureCalculator::calculate($data, $user->secret_key);
    }

    private function filteredByUser(User $user)
    {
        return function (TransactionFee $withdrawFee) use ($user) {
            return optional($withdrawFee->user)->is($user);
        };
    }
}

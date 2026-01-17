<?php

namespace App\Http\Resources\ThirdParty;

use App\Model\Channel;
use App\Model\UserChannel;
use App\Model\TransactionFee;
use App\Model\Transaction;
use App\Model\User;
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
                'system_order_number' => $item->system_order_number,
                'order_number'        => $item->order_number,
                'status'              => $item->status,
                'amount'              => $item->amount,
                'fee'                 => $item->from ? $item->transactionFees->filter($this->filteredByUser($item->from))->first()->fee : "0.00",
                'username'            => $item->to->username ?? '',
                'note'                => $item->channel->note_enable ?? '',
                'notify_url'          => $item->notify_url,
                'created_at'          => $item->created_at->toIso8601String(),
                'confirmed_at'        => optional($item->confirmed_at)->toIso8601String() ?? '',
            ];
            $list[] = $tmp;
        }

        $data = [
            'list' => $list,
            'now_page' => $this->resource->currentPage(),
            'total_page' => $this->resource->lastPage(),
            'count' => $this->resource->perPage(),
            'total_count' => $this->resource->total(),
        ];
        $user = $this->resource->first()->to;

        // 生成簽名
        $data['sign'] = $this->generateSign($user, $data);

        // 自定義返回結構
        return [
            'data' => $data,
            'http_status_code' => 201,
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

        ksort($data);

        return md5(urldecode(http_build_query($data) . '&secret_key=' . $user->secret_key));
    }

    private function filteredByUser(User $user)
    {
        return function (TransactionFee $withdrawFee) use ($user) {
            return optional($withdrawFee->user)->is($user);
        };
    }
}

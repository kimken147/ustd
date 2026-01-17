<?php

namespace App\Http\Resources\Admin;

use App\Models\TransactionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantTransactionGroup extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                      => $this->getKey(),
            'name'                    => $this->name,
            'username'                => $this->username,
            'transaction_groups' => $this->whenLoaded('transactionGroups', function () {
                return $this->transactionGroups->map(function (TransactionGroup $transactionGroup) {
                    return [
                        'id'                => $transactionGroup->getKey(),
                        'provider_name'     => $transactionGroup->worker->name,
                        'provider_username' => $transactionGroup->worker->username,
                        'personal_enable'   => $transactionGroup->personal_enable,
                    ];
                });
            }),
        ];
    }
}

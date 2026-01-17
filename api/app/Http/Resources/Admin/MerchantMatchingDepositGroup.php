<?php

namespace App\Http\Resources\Admin;

use App\Models\TransactionGroup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantMatchingDepositGroup extends JsonResource
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
            'matching_deposit_groups' => $this->whenLoaded('matchingDepositGroups', function () {
                return $this->matchingDepositGroups->map(function (TransactionGroup $matchingDepositGroup) {
                    return [
                        'id'                => $matchingDepositGroup->getKey(),
                        'provider_name'     => $matchingDepositGroup->worker->name,
                        'provider_username' => $matchingDepositGroup->worker->username,
                        'personal_enable'   => $matchingDepositGroup->personal_enable,
                    ];
                });
            }),
        ];
    }
}

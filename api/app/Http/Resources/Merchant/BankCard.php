<?php

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Resources\Json\JsonResource;

class BankCard extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                    => $this->getKey(),
            'status'                => $this->status,
            'bank_card_holder_name' => $this->bank_card_holder_name,
            'bank_card_number'      => $this->bank_card_number,
            'bank_name'             => $this->bank_name,
            'bank_province'         => $this->bank_province,
            'bank_city'             => $this->bank_city,
            'created_at'            => $this->created_at->toIso8601String(),
            'name'                  => $this->getName()
        ];
    }

    public function getName()
    {
        $name = $this->bank_name . '-' . $this->bank_card_number;
        if ($this->bank_card_holder_name) {
            $name = $name . '-' . $this->bank_card_holder_name;
        }

        return $name;
    }
}

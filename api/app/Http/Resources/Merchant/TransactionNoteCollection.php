<?php

namespace App\Http\Resources\Merchant;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TransactionNoteCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}

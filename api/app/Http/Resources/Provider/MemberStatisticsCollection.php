<?php

namespace App\Http\Resources\Provider;

use Illuminate\Http\Resources\Json\ResourceCollection;

class MemberStatisticsCollection extends ResourceCollection
{
    public function toArray($request)
    {
        return parent::toArray($request);
    }
}

<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Resources\ThirdParty\Rate;
use App\Utils\UsdtUtil;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;

class UsdtController extends BaseController
{
    public function rate($coin='USDT', $currency='CNY')
    {
        $util = app(UsdtUtil::class);

        $result = $util->getRate($currency);

        return Rate::make([
            $coin => $result['rate']
        ])
        ->additional([
            'http_status_code' => 200,
            'message'          => __('common.Query successful'),
        ])
        ->response()
        ->setStatusCode(Response::HTTP_OK);
    }
}

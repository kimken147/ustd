<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
//use App\Http\Requests\ListWalletHistoryRequest;
//use App\Http\Resources\WalletHistoryCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use App\Utils\UsdtUtil;

class UsdtRateController extends Controller
{
    public function index(UsdtUtil $usdtUti)
    {
        $data = $usdtUti->getRate();
        return $data;
    }
}
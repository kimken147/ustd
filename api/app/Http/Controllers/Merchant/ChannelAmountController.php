<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelAmountCollection;
use App\Model\ChannelAmount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChannelAmountController extends Controller
{

    public function index(Request $request)
    {
        $this->validate($request, [
            'no_paginate' => 'bool',
        ]);

        $channelAmounts = ChannelAmount::orderBy('channel_code')->orderBy(DB::raw('max_amount - min_amount'));

        return ChannelAmountCollection::make($request->boolean('no_paginate') ? $channelAmounts->get() : $channelAmounts->paginate(20));
    }
}

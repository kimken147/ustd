<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelAmountCollection;
use App\Models\ChannelAmount;
use Illuminate\Http\Request;

class ChannelAmountController extends Controller
{

    public function index(Request $request)
    {
        $this->validate($request, [
            'no_paginate' => 'bool',
        ]);

        $channelAmounts = ChannelAmount::orderBy('channel_code')->orderByAmountRange();

        return ChannelAmountCollection::make($request->boolean('no_paginate') ? $channelAmounts->get() : $channelAmounts->paginate(20));
    }
}

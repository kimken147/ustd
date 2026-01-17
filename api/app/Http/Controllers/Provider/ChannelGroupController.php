<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\ChannelGroupCollection;
use App\Model\ChannelGroup;
use Illuminate\Http\Request;

class ChannelGroupController extends Controller
{

    public function index(Request $request)
    {
        $this->validate($request, [
            'no_paginate' => 'bool',
        ]);

        $channelGroups = ChannelGroup::whereHas("channel", function ($q) {
            $q->where("third_exclusive_enable", false);
        });

        return ChannelGroupCollection::make(
            $request->boolean('no_paginate') ? $channelGroups->get() : $channelGroups->paginate(20)
        );
    }
}

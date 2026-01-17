<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\ChannelGroupCollection;
use App\Model\ChannelGroup;
use Illuminate\Http\Request;

class ChannelGroupController extends Controller
{

    public function index(Request $request)
    {
        $this->validate($request, [
            'no_paginate' => 'bool',
        ]);

        $channelGroups = ChannelGroup::query();

        return ChannelGroupCollection::make(
            $request->boolean('no_paginate') ? $channelGroups->get() : $channelGroups->paginate(20)
        );
    }
}

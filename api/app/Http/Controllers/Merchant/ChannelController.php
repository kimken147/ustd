<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListChannelRequest;
use App\Http\Resources\ChannelCollection;
use App\Model\Channel;

class ChannelController extends Controller
{
    public function index(ListChannelRequest $request)
    {
        $query = Channel::when($request->type,
            function ($builder, $type) {
                $builder->whereIn('type', $type);
            }
        )->orderByDesc('code');
        return ChannelCollection::make(
            $request->no_paginate ? $query->get() : $query->paginate(20)
        );
    }
}

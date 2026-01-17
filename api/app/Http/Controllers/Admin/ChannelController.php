<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListChannelRequest;
use App\Http\Resources\Admin\ChannelCollection;
use App\Models\Channel;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChannelController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:'.Permission::ADMIN_UPDATE_CHANNEL])->only('update');
    }

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

    public function update(Request $request, Channel $channel)
    {
        $this->validate($request, [
            'status'                     => 'nullable|boolean',
            'order_timeout'              => 'nullable|int|max:900',
            'order_timeout_enable'       => 'nullable|boolean',
            'transaction_timeout'        => 'nullable|int|max:900',
            'transaction_timeout_enable' => 'nullable|boolean',
            'real_name_enable'           => 'nullable|boolean',
            'note_enable'                => 'nullable|boolean',
            'floating'                   => 'nullable',
            'floating_enable'            => 'nullable|boolean',
        ]);

        $updatedAttributes = collect($request->only([
            'status', 'order_timeout', 'order_timeout_enable', 'transaction_timeout',
            'transaction_timeout_enable', 'real_name_enable', 'note_enable', 'note_type', 'floating', 'floating_enable',
        ]))->filter(function ($value) {
            return !is_null($value);
        })->toArray();

        abort_if(
            count($updatedAttributes)
            && !$channel->update($updatedAttributes),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        return \App\Http\Resources\Admin\Channel::make($channel);
    }
}

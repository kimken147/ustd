<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\NotificationCollection;
use App\Model\Notification;
use App\Model\User;
use App\Utils\AmountDisplayTransformer;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{

    public function index(Request $request)
    {
        $this->validate($request, [
            'started_at'           => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'ended_at'             => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
        ]);

        $startedAt = $request->has('started_at') ? Carbon::parse($request->started_at) : Carbon::now()->startOfDay();
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        abort_if(
            now()->diffInMonths($startedAt) > 1,
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        $query = Notification::when($request->mobile, function ($builder, $mobile) {
            $builder->where('mobile', 'like', "%{$mobile}%");
        })
        ->when($request->content, function ($builder, $content) {
            $builder->where('notification', 'like', "%{$content}%");
        })
        ->whereBetween('created_at', [$startedAt, $endedAt]);

        $query->orderByDesc('id');

        return NotificationCollection::make(
            $request->no_paginate ? $query->get() : $query->paginate(20)
        );
    }

    public function show(Notification $transaction)
    {
        abort_if(!$transaction->from->is(auth()->user()), Response::HTTP_NOT_FOUND);

        return \App\Http\Resources\Provider\Notification::make($transaction->load('from', 'to', 'transactionFees.user', 'channel'));
    }

    public function update($id)
    {
        return Notification::where('id',$id)->update(['error' => 0]);;
    }
}

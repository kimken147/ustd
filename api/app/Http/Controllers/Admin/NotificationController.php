<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\NotificationCollection;
use App\Models\Notification;
use App\Models\User;
use App\Utils\AmountDisplayTransformer;
use App\Utils\DateRangeValidator;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

        $dateRange = DateRangeValidator::parse($request, now()->startOfDay())
            ->validateMonths(1);
        $startedAt = $dateRange->startedAt;
        $endedAt = $dateRange->endedAt;

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

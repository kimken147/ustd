<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\NotificationCollection;
use App\Models\Notification;
use App\Models\User;
use App\Utils\AmountDisplayTransformer;
use App\Utils\DateRangeValidator;
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
            'channel_code'         => ['nullable', 'array'],
            'status'               => ['nullable', 'array'],
            'provider_device_name' => ['nullable', 'string'],
            'with_stats'           => ['nullable', 'boolean'],
        ]);

        DateRangeValidator::parse($request)
            ->validateMonths(2)
            ->validateDays(31);

        auth()->user()->update([
            'last_activity_at' => now(),
        ]);

        $transactions = Notification::where('error', 1)
            ->select(['notifications.*'])
            ->leftJoin('transactions','transaction_id','transactions.id')
            ->with("tran")
            ->whereIn('transactions.from_id',auth()->user()->getDescendantsId());

        return NotificationCollection::make($transactions->paginate(20));
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

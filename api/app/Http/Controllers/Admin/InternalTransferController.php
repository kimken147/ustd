<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InternalTransferCollection;
use App\Http\Resources\Admin\InternalTransfer;
use App\Models\Permission;
use App\Models\Transaction;
use App\Utils\TransactionUtil;
use App\Builders\Transaction as TransactionBuilder;
use App\Exceptions\TransactionLockerNotYouException;
use App\Services\InternalTransfer\InternalTransferService;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class InternalTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware([
            "permission:" . Permission::ADMIN_UPDATE_TRANSACTION,
        ])->only("update");
    }

    public function index(Request $request)
    {
        $this->validate($request, [
            "started_at" => ["nullable", "date_format:" . DateTimeInterface::ATOM],
            "ended_at" => ["nullable", "date_format:" . DateTimeInterface::ATOM],
            "channel_code" => ["nullable", "array"],
            "status" => ["nullable", "array"],
        ]);

        $startedAt = $request->started_at
            ? optional(Carbon::make($request->started_at))->tz(config("app.timezone"))
            : now()->startOfDay();
        $endedAt = $request->ended_at
            ? Carbon::make($request->ended_at)->tz(config("app.timezone"))
            : now()->endOfDay();

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            __('common.No data found')
        );

        abort_if(
            !$startedAt || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            __('common.Date range limited to one month')
        );

        $builder = new TransactionBuilder();
        $transactions = $builder->internalTransfer($request);

        $transactions->with("from", "transactionNotes", "toChannelAccount");

        return InternalTransferCollection::make($transactions->paginate(20));
    }

    public function store(Request $request, InternalTransferService $service)
    {
        $this->validate($request, [
            "account_id" => "required|numeric",
            "amount" => "required|numeric",
            "bank_name" => "required|string",
            "bank_card_holder_name" => "required|string",
        ]);

        $transfer = $service->execute($request);

        return InternalTransfer::make($transfer);
    }

    public function update(
        Request $request,
        Transaction $transfer,
        TransactionUtil $transactionUtil
    ) {
        abort_if(
            $transfer->type != Transaction::TYPE_INTERNAL_TRANSFER,
            Response::HTTP_BAD_REQUEST,
            __('common.Invalid order number')
        );

        $this->validate($request, [
            "status" => [
                "int",
                Rule::in([
                    Transaction::STATUS_MANUAL_SUCCESS,
                    Transaction::STATUS_FAILED,
                    Transaction::STATUS_REVIEW_PASSED,
                ]),
            ],
            "note" => ["nullable", "string", "max:50"],
            "locked" => ["boolean"],
            "to_id" => ["nullable", "int"],
        ]);

        $transactionUtil->supportLockingLogics($transfer, $request);

        if ($request->input("status") === Transaction::STATUS_FAILED) {
            $this->validate($request, [
                "note" => ["string", "max:50"],
            ]);

            try {
                $transactionUtil->markAsFailed(
                    $transfer,
                    auth()->user()->realUser(),
                    $request->input("note"),
                    false
                );
            } catch (TransactionLockerNotYouException $e) {
                abort(Response::HTTP_FORBIDDEN, __('common.Operation error, locked by different user'));
            }
        }

        if ($request->input("status") === Transaction::STATUS_MANUAL_SUCCESS) {
            if ($request->has("_search1")) {
                $search1 = $request->input("_search1");
                abort_if(
                    Transaction::where("_search1", $search1)->exists(),
                    Response::HTTP_BAD_REQUEST,
                    __('common.Already duplicated')
                );
                abort_if(
                    $transfer->_search1,
                    Response::HTTP_BAD_REQUEST,
                    __('common.Already manually processed')
                );

                $transfer->update(["_search1" => $request->input("_search1")]);
            }

            try {
                $transactionUtil->markAsSuccess(
                    $transfer,
                    auth()->user()->realUser()
                );
            } catch (TransactionLockerNotYouException $e) {
                abort(Response::HTTP_FORBIDDEN, __('common.Operation error, locked by different user'));
            }
        }

        if ($request->note) {
            $transfer->update(["note" => $request->note]);
        }

        return InternalTransfer::make(
            $transfer
                ->refresh()
                ->load("from", "transactionNotes", "toChannelAccount")
        );
    }
}

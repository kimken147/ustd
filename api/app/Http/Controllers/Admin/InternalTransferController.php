<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\InternalTransferCollection;
use App\Http\Resources\Admin\InternalTransfer;
use App\Jobs\SettleDelayedProviderCancelOrder;
use App\Jobs\NotifyTransaction;
use App\Model\Channel;
use App\Model\Permission;
use App\Model\Transaction;
use App\Model\User;
use App\Model\UserChannelAccount;
use App\Model\UserChannel;
use App\Utils\AmountDisplayTransformer;
use App\Utils\TransactionUtil;
use App\Utils\TransactionFactory;
use App\Builders\Transaction as TransactionBuilder;
use App\Exceptions\TransactionLockerNotYouException;
use App\Jobs\GcashDaifu;
use App\Jobs\MayaDaifu;
use App\Model\Bank;
use App\Utils\BCMathUtil;
use App\Utils\UserChannelAccountUtil;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            "started_at" => [
                "nullable",
                "date_format:" . DateTimeInterface::ATOM,
            ],
            "ended_at" => [
                "nullable",
                "date_format:" . DateTimeInterface::ATOM,
            ],
            "channel_code" => ["nullable", "array"],
            "status" => ["nullable", "array"],
        ]);

        $startedAt = $request->started_at
            ? optional(Carbon::make($request->started_at))->tz(
                config("app.timezone")
            )
            : now()->startOfDay();
        $endedAt = $request->ended_at
            ? Carbon::make($request->ended_at)->tz(config("app.timezone"))
            : now()->endOfDay();

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            "查无资料"
        );

        abort_if(
            !$startedAt || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            "时间区间最多一次筛选一个月，请重新调整时间"
        );

        $builder = new TransactionBuilder();
        $transactions = $builder->internalTransfer($request);

        $transactions->with("from", "transactionNotes", "toChannelAccount");

        return InternalTransferCollection::make($transactions->paginate(20));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            "account_id" => "nullable|numeric",
            "amount" => "required|numeric",
            "bank_name" => "required|string",
            "bank_card_holder_name" => "required|string",
        ]);
        $account = UserChannelAccount::findOrFail($request->account_id);
        $bank = Bank::where("name", $request->bank_name)
            ->orWhere("code", $request->bank_name)
            ->firstOrFail();

        $exextraWithdrawFee = 0;
        if ($account->channel_code != $bank->name) {
            if ($account->channel_code != "MAYA") {
                $exextraWithdrawFee = 15;
            }
        }

        $fromChannelAccount = [
            UserChannelAccount::DETAIL_KEY_BANK_NAME => $request->input(
                "bank_name"
            ),
            UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER => $request->input(
                "bank_card_number"
            ),
            UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME => $request->input(
                "bank_card_holder_name"
            ),
            "extra_withdraw_fee" => $exextraWithdrawFee,
        ];

        $data = [
            "from_id" => 0,
            "from_wallet_id" => 0,
            "to_id" => 0,
            "to_channel_account_id" => null,
            "type" => Transaction::TYPE_INTERNAL_TRANSFER,
            "status" => Transaction::STATUS_MATCHING,
            "notify_status" => Transaction::NOTIFY_STATUS_NONE,
            "to_account_mode" => null,
            "from_channel_account" => $fromChannelAccount,
            "to_channel_account" => [],
            "amount" => $request->input("amount"),
            "floating_amount" => $request->input("amount"),
            "actual_amount" => 0,
            "usdt_rate" => 0,
            "channel_code" => null,
            "order_number" => $request->input(
                "order_id",
                chr(mt_rand(65, 90)) .
                    chr(mt_rand(65, 90)) .
                    chr(mt_rand(65, 90)) .
                    date("YmdHis") .
                    rand(100, 999)
            ),
            "note" => $request->input("note"),
        ];

        if ($request->has("account_id")) {
            $exists = Transaction::where("to_channel_account_id", $account->id)
                ->where("status", Transaction::STATUS_PAYING)
                ->where("created_at", ">", now()->subDay())
                ->exists();
            abort_if(
                $exists,
                Response::HTTP_FORBIDDEN,
                "{$account->account} 正在出款，请稍候再试"
            );

            $to = $account->user;

            $data["to_id"] = $to->id;
            $data["to_channel_account_id"] = $account->id;
            $data["to_channel_account"] = array_merge($account->detail, [
                "channel_code" => $account->channel_code,
            ]);
            $data["status"] = Transaction::STATUS_PAYING;
            $data["matched_at"] = now();
        }

        try {
            $transfer = Transaction::create($data);
            if ($account->channel_code == "MAYA") {
                MayaDaifu::dispatch($transfer, "init");
            } else {
                GcashDaifu::dispatch($transfer, "init");
            }

            $amount = app(BCMathUtil::class)->add(
                $transfer->amount,
                data_get($fromChannelAccount, "extra_withdraw_fee", 0)
            );
            app(UserChannelAccountUtil::class)->updateTotal(
                $account->id,
                $amount,
                true
            );

            return InternalTransfer::make($transfer);
        } catch (\Exception $e) {
            abort(Response::HTTP_BAD_REQUEST, "建立出款失败");
        }
    }

    public function update(
        Request $request,
        Transaction $transfer,
        TransactionUtil $transactionUtil
    ) {
        abort_if(
            $transfer->type != Transaction::TYPE_INTERNAL_TRANSFER,
            Response::HTTP_BAD_REQUEST,
            "单号错误"
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
                    auth()
                        ->user()
                        ->realUser(),
                    $request->input("note"),
                    false
                );
            } catch (TransactionLockerNotYouException $e) {
                abort(Response::HTTP_FORBIDDEN, "操作错误，与锁定人不符");
            }
        }

        if ($request->input("status") === Transaction::STATUS_MANUAL_SUCCESS) {
            if ($request->has("_search1")) {
                $search1 = $request->input("_search1");
                abort_if(
                    Transaction::where("_search1", $search1)->exists(),
                    Response::HTTP_BAD_REQUEST,
                    "{$search1} 已重複"
                );
                abort_if(
                    $transfer->_search1,
                    Response::HTTP_BAD_REQUEST,
                    "{$search1} 已补单"
                );

                $transfer->update(["_search1" => $request->input("_search1")]);
            }

            try {
                $transactionUtil->markAsSuccess(
                    $transfer,
                    auth()
                        ->user()
                        ->realUser()
                );
            } catch (TransactionLockerNotYouException $e) {
                abort(Response::HTTP_FORBIDDEN, "操作错误，与锁定人不符");
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

    public function statistics(Request $request)
    {
        $this->validate($request, [
            "started_at" => [
                "nullable",
                "date_format:" . DateTimeInterface::ATOM,
            ],
            "ended_at" => [
                "nullable",
                "date_format:" . DateTimeInterface::ATOM,
            ],
            "channel_code" => ["nullable", "array"],
            "status" => ["nullable", "array"],
            "notify_status" => ["nullable", "array"],
            "provider_device_name" => ["nullable", "string"],
            "provider_device_hash_id" => ["nullable", "string"],
            "thirdchannel_id" => "nullable",
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(
            config("app.timezone")
        );
        $endedAt = $request->ended_at
            ? Carbon::make($request->ended_at)->tz(config("app.timezone"))
            : now();

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            "查无资料"
        );

        abort_if(
            !$startedAt || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            "时间区间最多一次筛选一个月，请重新调整时间"
        );

        $builder = new TransactionBuilder();
        $transactions = $builder->internalTransfer($request);

        $stats = (clone $transactions)
            ->useIndex("transactions_query_1")
            ->first([
                DB::raw("SUM(floating_amount) AS total_amount"),
                DB::raw(
                    "SUM(CASE WHEN status IN (4, 5) THEN 1 ELSE 0 END) AS total_success"
                ),
            ]);

        return response()->json([
            "total_amount" => AmountDisplayTransformer::transform(
                $stats->total_amount ?? "0.00"
            ),
            "total_success" => $stats->total_success ?? 0,
        ]);
    }
}

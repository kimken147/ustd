<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Withdraw;
use App\Http\Resources\Admin\WithdrawCollection;
use App\Jobs\NotifyTransaction;
use App\Models\FeatureToggle;
use App\Models\Permission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\BannedRealname;
use App\Models\TransactionGroup;
use App\Models\TransactionNote;
use App\Repository\FeatureToggleRepository;
use App\Utils\AmountDisplayTransformer;
use App\Utils\TransactionUtil;
use App\Builders\Transaction as TransactionBuilder;
use App\Models\MerchantThirdChannel;
use App\Models\ThirdChannel;
use App\Utils\BCMathUtil;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Exceptions\TransactionLockerNotYouException;

class WithdrawController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:' . Permission::ADMIN_UPDATE_WITHDRAW])->only('update');
    }

    public function index(Request $request, FeatureToggleRepository $featureToggleRepository)
    {
        // 暫時加上檢查以維持向下相容
        if ($request->type && !is_array($request->type)) {
            $request->merge([
                'type' => [$request->type],
            ]);
        }

        $this->validate($request, [
            'started_at'    => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at'      => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'type.*'        => [
                'int',
                Rule::in([
                    Transaction::TYPE_PAUFEN_WITHDRAW,
                    Transaction::TYPE_NORMAL_WITHDRAW,
                    Transaction::TYPE_VIRTUAL_PAUFEN_WITHDRAW_AVAILABLE_FOR_ADMIN
                ])
            ],
            'sub_type'      => ['nullable', 'array'],
            'status'        => ['nullable', 'array'],
            'notify_status' => ['nullable', 'array'],
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        abort_if(
            now()->diffInMonths($startedAt) > 8,
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            !$startedAt
                || $startedAt->diffInDays($endedAt) > 91,
            Response::HTTP_BAD_REQUEST,
            '时间区间最多一次筛选三个月，请重新调整时间'
        );

        if (empty($request->type)) {
            $request->merge([
                'type' => [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW],
            ]);
        }

        $builder = new TransactionBuilder;
        $withdraws = $builder->withdraws($request);

        // 總金額只包含未拆單+子訂單
        $stats = (clone $withdraws)->where(function (Builder $builder) use ($withdraws) {
            $builder->whereNotNull('parent_id')
                ->orWhereNotIn('id', (clone $withdraws)->whereNotNull('parent_id')->get()->pluck('parent_id'));
        });

        $stats = $stats->first(
            [
                DB::raw(
                    'SUM(amount) AS total_amount'
                ),
            ]
        );
        $totalWithdrawFees = (clone $withdraws)->join('transaction_fees', 'transactions.id', '=', 'transaction_fees.transaction_id');

        $totalFee = (clone $totalWithdrawFees)->whereNull('transaction_fees.thirdchannel_id')->sum('transaction_fees.actual_fee');
        $totalProfit = (clone $totalWithdrawFees)->where('transaction_fees.user_id', 0)->sum('transaction_fees.actual_profit');
        $thirdChannelFee = (clone $totalWithdrawFees)->whereNotNull('transaction_fees.thirdchannel_id')->sum('transaction_fees.actual_fee');

        $withdraws->with('from', 'to', 'transactionFees.user', 'parent', 'children', 'siblings', 'transactionNotes.user', 'lockedBy', 'thirdChannel', 'toChannelAccount', 'toChannelAccount.device', 'certificateFiles');

        $perPage = $request->input('per_page', 20);
        return WithdrawCollection::make($withdraws->paginate($perPage))->additional([
            'meta' => [
                'has_new_withdraws' => $this->hasNewWithdraws(),
                'total_amount'      => AmountDisplayTransformer::transform($stats->total_amount ?? '0.00'),
                'total_fee'      => AmountDisplayTransformer::transform($totalFee ?? '0.00'),
                'total_profit'      => AmountDisplayTransformer::transform($totalProfit ?? '0.00'),
                'third_channel_fee' => AmountDisplayTransformer::transform($thirdChannelFee ?? '0.00'),
                'banned_realnames'  => BannedRealname::where('type', BannedRealname::TYPE_WITHDRAW)->get()->pluck('realname')
            ]
        ]);
    }

    private function hasNewWithdraws()
    {
        $userId = auth()->user()->realUser()->getKey();
        $adminLastReadAt = Carbon::make(Cache::get("admin_{$userId}_withdraws_read_at"));
        $withdrawsAddedAt = Carbon::make(Cache::get('admin_withdraws_added_at'));

        Cache::put("admin_{$userId}_withdraws_read_at", now(), now()->addSeconds(60));

        if (!$adminLastReadAt && $withdrawsAddedAt) {
            return true;
        }

        return $adminLastReadAt && $withdrawsAddedAt && ($adminLastReadAt->lt($withdrawsAddedAt));
    }

    private function markAsPaufenWithdraw(Transaction $withdraw, Request $request, TransactionUtil $transactionUtil, bool $shouldLock = true)
    {
        $user = null;

        if ($request->filled('to_id')) {
            $user = User::find($request->input('to_id'));

            abort_if(!$user, Response::HTTP_BAD_REQUEST, __('common.User not found'));
        }

        return $transactionUtil->markAsPaufenWithdraw($withdraw, $user, $shouldLock);
    }

    public function show(Transaction $withdraw)
    {
        return Withdraw::make($withdraw->load('from', 'to', 'transactionFees.user', 'parent', 'children', 'transactionNotes.user'));
    }

    public function update(
        Request $request,
        Transaction $withdraw,
        TransactionUtil $transactionUtil,
        FeatureToggleRepository $featureToggleRepository
    ) {
        $this->validate($request, [
            'status'              => ['int', Rule::in([Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_FAILED, Transaction::STATUS_REVIEW_PASSED])],
            'notify_status'       => ['int', Rule::in(Transaction::NOTIFY_STATUS_PENDING)],
            'note'                => ['nullable', 'string', 'max:50'],
            'locked'              => ['boolean'],
            'to_id'               => ['nullable', 'int'],
            'to_thirdchannel_ id' => ['nullable', 'int'],
        ]);

        // 若開關沒開，則表示訂單永遠不會超時，則永遠不該提供鎖定功能
        $paufenWithdrawShouldTimedOut = $featureToggleRepository->enabled(FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT);

        abort_if(
            $withdraw->type === Transaction::TYPE_PAUFEN_WITHDRAW
                && !$paufenWithdrawShouldTimedOut,
            Response::HTTP_BAD_REQUEST,
            __('withdraw.Please setting withdraw')
        );

        abort_if(
            $withdraw->type === Transaction::TYPE_PAUFEN_WITHDRAW &&
                $withdraw->to_id &&
                !$featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM),             // 啟用自動代付則不會出現這個錯誤
            Response::HTTP_BAD_REQUEST,
            __('withdraw.Already grabbed the order')
        );

        abort_if(
            $withdraw->thridchannel && $request->has('locked'),
            Response::HTTP_BAD_REQUEST,
            __('withdraw.No locking')
        );

        if ($request->input('status') === Transaction::STATUS_FAILED) {
            $this->validate($request, [
                'note' => ['string', 'max:50'],
            ]);

            try {
                $transactionUtil->markAsFailed($withdraw, auth()->user()->realUser(), $request->input('note'), false);
            } catch (TransactionLockerNotYouException $e) {
                abort(Response::HTTP_FORBIDDEN, __('common.Operation error, locked by different user'));
            }
        }

        if ($request->input('status') === Transaction::STATUS_MANUAL_SUCCESS) {
            if ($request->has('_search1')) {
                $search1 = $request->input('_search1');
                abort_if(Transaction::where('_search1', $search1)->exists(), Response::HTTP_BAD_REQUEST, __('common.Already duplicated'));
                abort_if($withdraw->_search1, Response::HTTP_BAD_REQUEST, __('common.Already manually processed'));

                $withdraw->update(['_search1' => $request->input('_search1')]);
            }

            try {
                $transactionUtil->markAsSuccess($withdraw, auth()->user()->realUser());
            } catch (TransactionLockerNotYouException $e) {
                abort(Response::HTTP_FORBIDDEN, __('common.Operation error, locked by different user'));
            }
        }

        // 訂單審核也會走這條
        if ($request->input('child_withdraws')) {
            $this->validate($request, [
                'child_withdraws'          => 'required|array',
                'child_withdraws.*.type'   => [
                    'required',
                    Rule::in([Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW])
                ],
                'child_withdraws.*.amount' => ['required', 'numeric'],
                'child_withdraws.*.to_id'  => ['nullable']
            ]);

            $withdraw = $transactionUtil->separateWithdraw($withdraw, collect($request->input('child_withdraws')), false);
        }

        if ($request->input('status') === Transaction::STATUS_REVIEW_PASSED) {
            abort_if(
                !$request->input('child_withdraws') // 上面拆單後狀態就會是支付中，因此跳過這個檢查
                    && $withdraw->status !== Transaction::STATUS_PENDING_REVIEW,
                Response::HTTP_BAD_REQUEST,
                '订单已审核'
            );

            if (!$request->input('child_withdraws')) {
                $this->validate($request, [
                    'type' => ['required', Rule::in([Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW])]
                ]);

                if ($request->input('type') === Transaction::TYPE_NORMAL_WITHDRAW) {
                    $withdraw = $transactionUtil->markAsNormalWithdraw($withdraw, $withdraw->note, false);
                } else {
                    $this->validate($request, [
                        'to_id' => 'nullable|present|int|min:1',
                    ]);

                    $withdraw = $this->markAsPaufenWithdraw($withdraw, $request, $transactionUtil, false);
                }
            }
        }

        $transactionUtil->supportLockingLogics(
            $withdraw,
            $request,
            function () use ($transactionUtil, $withdraw, $featureToggleRepository) {
                $paufenWithdrawTimedOutInSeconds = $featureToggleRepository->valueOf(FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT);

                // 跑分提現未搶單且超時，按鎖定時直接轉為一般提現
                if ($withdraw->type === Transaction::TYPE_PAUFEN_WITHDRAW && !$withdraw->to_id) {
                    $updatedRow = Transaction::where('id', $withdraw->getKey())
                        ->where('type', Transaction::TYPE_PAUFEN_WITHDRAW)
                        ->whereNull('to_id')
                        ->where('status', Transaction::STATUS_MATCHING)
                        ->whereNull('locked_at')
                        ->where('created_at', '<=', now()->subSeconds($paufenWithdrawTimedOutInSeconds))
                        ->update([
                            'to_id'  => 0,
                            'type'   => Transaction::TYPE_NORMAL_WITHDRAW,
                            'status' => Transaction::STATUS_PAYING,
                        ]);

                    abort_if(
                        $updatedRow !== 1,
                        Response::HTTP_BAD_REQUEST,
                        __('common.Conflict! Please try again later')
                    );
                }
            }
        );

        if (
            in_array(
                $withdraw->notify_status,
                [Transaction::NOTIFY_STATUS_SUCCESS, Transaction::NOTIFY_STATUS_FAILED, Transaction::NOTIFY_STATUS_PENDING]
            )
            && $request->notify_status === Transaction::NOTIFY_STATUS_PENDING
        ) {
            abort_if(
                !$withdraw->update(['notify_status' => $request->notify_status]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );

            NotifyTransaction::dispatch($withdraw);
        }

        if ($request->note) {
            $withdraw->update(['note' => $request->note]);
        }

        // 轉為碼商出
        if (!$request->has('status') && $request->has('to_id')) {
            if ($request->input('to_id') > 0) { // 如果指定码商，需要检查是否符合
                $transaction = $withdraw->where('id', $request->id)->first();

                $toId = $request->to_id;
                $toAncestors = User::ancestorsAndSelf($toId);
                $groups = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)->get();

                $binded = $groups->where('owner_id', $transaction->from_id)->where('personal_enable', false)->where('worker_id', $toId)->isNotEmpty();
                $ancestorsBinded = $groups->where('owner_id', $transaction->from_id)->where('personal_enable', true)->whereIn('worker_id', $toAncestors->pluck('id'))->isNotEmpty();
                $noBinded = $groups->where('owner_id', $transaction->from_id)->isEmpty() && // 商戶沒綁快充專線 且
                    $groups->where('worker_id', $toId)->isEmpty() && // 碼商沒綁快充專線 且
                    $groups->where('personal_enable', true)->whereIn('worker_id', $toAncestors->pluck('id'))->isEmpty(); // 碼商上級沒有其它的個人線

                abort_unless($binded || $ancestorsBinded || $noBinded, Response::HTTP_BAD_REQUEST, '请检查快充专线设置');
            }

            $withdraw = $this->markAsPaufenWithdraw($withdraw, $request, $transactionUtil);
        }

        if (!$request->has('status') && $request->has('to_thirdchannel_id')) { // 轉為三方出
            $withdraw = $this->markAsThirdChannelWithdraw($withdraw, $request, $transactionUtil);
        }

        return Withdraw::make($withdraw->refresh()->load('from', 'to', 'transactionFees.user', 'parent', 'children'));
    }

    private function markAsThirdChannelWithdraw(Transaction $withdraw, Request $request, TransactionUtil $transactionUtil, bool $shouldLock = true)
    {
        $merchantThirdChannel = MerchantThirdChannel::where('owner_id', $withdraw->from_id)
            ->where('thirdchannel_id', $request->to_thirdchannel_id)
            ->where('daifu_min', '<=', $withdraw->amount)
            ->where('daifu_max', '>=', $withdraw->amount)
            ->first();

        abort_unless($merchantThirdChannel, Response::HTTP_BAD_REQUEST, __('withdraw.Check third channel setting'));

        $thirdChannel = $merchantThirdChannel->thirdChannel;

        abort_unless($thirdChannel, Response::HTTP_BAD_REQUEST, __('common.Third channel not found'));

        abort_unless($withdraw->from->third_channel_enable, Response::HTTP_BAD_REQUEST, __('withdraw.Third channel disabled'));

        abort_unless($thirdChannel->status == ThirdChannel::STATUS_ENABLE && $thirdChannel->canDaifu(), Response::HTTP_BAD_REQUEST, '请检查三方通道是否设置');

        abort_if(!in_array($withdraw->status, [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_PAYING]), Response::HTTP_BAD_REQUEST, __('withdraw.Status cannot be changed'));

        $path = "App\ThirdChannel\\" . $thirdChannel->class;
        $api = new $path();

        preg_match("/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/", $api->daifuUrl, $url);

        $new_data = new \stdClass();

        $account = $withdraw->from_channel_account;

        $new_data->bank_card_holder_name = $account['bank_card_holder_name'];
        $new_data->bank_card_number = $account['bank_card_number'];
        $new_data->bank_name = $account['bank_name'];
        $new_data->bank_province = $account['bank_province'];
        $new_data->bank_city = $account['bank_city'];
        $new_data->amount = $withdraw->amount;
        $new_data->order_number = $withdraw->order_number;

        $data = [
            'url'  => preg_replace("/{$url[1]}/", $thirdChannel->custom_url, $api->daifuUrl),
            'queryDaifuUrl'  => preg_replace("/{$url[1]}/", $thirdChannel->custom_url, $api->queryDaifuUrl),
            'queryBalanceUrl'  => preg_replace("/{$url[1]}/", $thirdChannel->custom_url, $api->queryBalanceUrl),
            'callback_url'  => config('app.url') . '/api/v1/callback/' . $withdraw->order_number,
            'merchant'  => $thirdChannel->merchant_id,
            'key'  => $thirdChannel->key,
            'key2'  => $thirdChannel->key2,
            'key3'  => $thirdChannel->key3,
            "key4" => $thirdChannel->key4,
            'proxy' => $thirdChannel->proxy,
            'request' => $new_data,
            'thirdchannelId' => $thirdChannel->id,
            'system_order_number' => $withdraw->order_number,
        ];

        if (property_exists($api, "alipayDaifuUrl")) {
            $data["alipayDaifuUrl"] = preg_replace("/{$url[1]}/", $thirdChannel->custom_url, $api->alipayDaifuUrl);
        }

        $balance = $api->queryBalance($data);
        abort_unless($balance > $request->amount, Response::HTTP_BAD_REQUEST, '三方余额不足');

        $result = $api->sendDaifu($data);

        if ($errorMessage = $result["msg"] ?? null) {
            TransactionNote::create([
                "user_id" => 0,
                "transaction_id" => $withdraw->id,
                "note" => $thirdChannel->name . ": " . $result['msg']
            ]);
        }

        $bcMath = new BCMathUtil;
        $fee = $bcMath->sum([ // 代付手續費為 0.X% + 單筆N元
            $bcMath->mulPercent($withdraw->amount, $merchantThirdChannel->daifu_fee_percent),
            $merchantThirdChannel->withdraw_fee,
        ]);

        if ($result['success']) {
            return $transactionUtil->markAsThirdChannelWithdraw($withdraw, $thirdChannel, $shouldLock);
        } else {
            $query = $api->queryDaifu($data);
            if (isset($query['success']) && $query['success'] && $query['status'] == Transaction::STATUS_PAYING) {
                return $transactionUtil->markAsThirdChannelWithdraw($withdraw, $thirdChannel, true);
            }
            if (isset($query['timeout']) && $query['timeout']) { // 超時
                return $transactionUtil->markAsThirdChannelWithdraw($withdraw, $thirdChannel, true);
            }
            abort(Response::HTTP_BAD_REQUEST, __('withdraw.Third party payment failed'));
        }
    }

    public function exportCsv(Request $request)
    {
        // 暫時加上檢查以維持向下相容
        if ($request->type && !is_array($request->type)) {
            $request->merge(['type' => explode(',', $request->type)]);
        }
        if ($request->status && !is_array($request->status)) {
            $request->merge(['status' => explode(',', $request->status)]);
        }
        if ($request->notify_status && !is_array($request->notify_status)) {
            $request->merge(['notify_status' => explode(',', $request->notify_status)]);
        }

        $this->validate($request, [
            'started_at'    => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at'      => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'type.*'        => [
                'int',
                Rule::in([
                    Transaction::TYPE_PAUFEN_WITHDRAW,
                    Transaction::TYPE_NORMAL_WITHDRAW,
                    Transaction::TYPE_VIRTUAL_PAUFEN_WITHDRAW_AVAILABLE_FOR_ADMIN
                ])
            ],
            'sub_type'      => ['nullable', 'array'],
            'status'        => ['nullable', 'array'],
            'notify_status' => ['nullable', 'array'],
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            !$startedAt
                || $startedAt->diffInDays($endedAt) > 91,
            Response::HTTP_BAD_REQUEST,
            '时间区间最多一次筛选三个月，请重新调整时间'
        );

        if (empty($request->type)) {
            $request->merge([
                'type' => [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW],
            ]);
        }

        $builder = new TransactionBuilder;
        $withdraws = $builder->withdraws($request)->get()->load('lockedBy', 'from', 'to', 'transactionFees', 'transactionFees.user');

        $statusTextMap = [
            1 => '已建立',
            '匹配中',
            '等待付款',
            '成功',
            '成功',
            '匹配超时',
            '支付超时',
            '失败',
        ];

        $notifyStatusTextMap = ['未通知', '等待发送', '发送中', '成功', '失败',];

        $typeMap = [[
            2 => '提现',
            201 => '提现（可锁定）',
            4 => '提现'
        ], [
            2 => '下发',
            201 => '下发（可锁定）',
            4 => '下发'
        ], [
            2 => '代付',
            201 => '代付（可锁定）',
            4 => '代付'
        ]];

        return response()->streamDownload(
            function () use ($withdraws, $statusTextMap, $notifyStatusTextMap, $typeMap) {
                $handle = fopen('php://output', 'w');
                fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // for UTF-8 BOM

                $columns = [
                    '出款账号',
                    '使用者资讯',
                    '银行名称',
                    '卡号',
                    '持卡人姓名',
                    '订单金额',
                    '商户订单号',
                    '建立时间',
                    '成功时间',
                    '订单状态',
                    '回调状态',
                    '回调时间',
                    '手续费'
                ];
                if (config('app.region') == 'ph') {
                    $columns[] = 'Ref No.';
                }
                if (config('app.region') == 'cn') {
                    $columns[] = '提现类型';
                }
                fputcsv($handle, $columns);

                foreach ($withdraws as $transaction) {
                    $value = [
                        data_get($transaction->to_channel_account, 'account'),
                        optional($transaction->from)->name,
                        data_get($transaction->from_channel_account, 'bank_name'),
                        data_get($transaction->from_channel_account, 'bank_card_number'),
                        data_get($transaction->from_channel_account, 'bank_card_holder_name'),
                        $transaction->amount,
                        $transaction->order_number,
                        $transaction->created_at->toIso8601String(),
                        optional($transaction->confirmed_at)->toIso8601String(),
                        data_get($statusTextMap, $transaction->status, '无'),
                        data_get($notifyStatusTextMap, $transaction->notify_status, '无'),
                        optional($transaction->notified_at)->toIso8601String(),
                        $transaction->transactionFees->firstWhere('user_id', 0)->profit
                    ];
                    if (config('app.region') == 'ph') {
                        $value[] = $transaction->_search1;
                    }
                    if (config('app.region') == 'cn') {
                        $value[] = $typeMap[$transaction->sub_type ?? 0][$transaction->type];
                    }
                    fputcsv($handle, $value);
                }

                fclose($handle);
            },
            '提现报表' . now()->format('Ymd') . '.csv'
        );
    }
}

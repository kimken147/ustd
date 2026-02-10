<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\RaceConditionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Deposit;
use App\Http\Resources\Admin\DepositCollection;
use App\Models\Permission;
use App\Models\Transaction;
use App\Utils\AmountDisplayTransformer;
use App\Utils\DateRangeValidator;
use App\Utils\TransactionUtil;
use App\Builders\Transaction as TransactionBuilder;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepositController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:' . Permission::ADMIN_UPDATE_DEPOSIT])->only('update');
    }

    public function index(Request $request)
    {
        $this->validate($request, [
            'started_at' => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at'   => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'status'     => ['nullable', 'array'],
            'type'       => [
                'nullable', 'int', Rule::in(Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT)
            ],
        ]);

        DateRangeValidator::parse($request)
            ->validateMonths(2)
            ->validateDays(31);

        $builder = new TransactionBuilder;
        $deposits = $builder->deposits($request);

        $stats = (clone $deposits)->first(
            [
                DB::raw(
                    'SUM(amount) AS total_amount'
                ),
            ]
        );

        return DepositCollection::make($deposits->paginate(20))
            ->additional([
                'meta' => [
                    'has_new_deposits' => $this->hasNewDeposits(),
                    'total_amount'     => AmountDisplayTransformer::transform($stats->total_amount ?? '0.00'),
                ]
            ]);
    }

    private function hasNewDeposits()
    {
        $userId = auth()->user()->realUser()->getKey();
        $adminLastReadAt = Carbon::make(Cache::get("admin_{$userId}_deposits_read_at"));
        $depositsAddedAt = Carbon::make(Cache::get('admin_deposits_added_at'));

        Cache::put("admin_{$userId}_deposits_read_at", now(), now()->addSeconds(60));

        if (!$adminLastReadAt && $depositsAddedAt) {
            return true;
        }

        return $adminLastReadAt && $depositsAddedAt && ($adminLastReadAt->lt($depositsAddedAt));
    }

    public function show(Transaction $deposit)
    {
        return Deposit::make($deposit->load('to', 'transactionNotes.user'));
    }

    public function update(Request $request, Transaction $deposit, TransactionUtil $transactionUtil)
    {
        $this->validate($request, [
            'status'               => [
                'int',
                Rule::in(Transaction::STATUS_PAYING, Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_FAILED)
            ],
            'locked'               => ['boolean'],
            'to_id'                => 'nullable|in:0',
            'note'                 => 'required_with:to_id|string|max:50',
            'delay_settle_hours'   => 'int|min:0|max:24',
            'delay_settle_minutes' => 'int',
        ]);

        if ($deposit->status === Transaction::STATUS_MANUAL_SUCCESS && $request->has('delay_settle_hours')) {
            abort_if($deposit->to_wallet_settled, Response::HTTP_BAD_REQUEST, '充值已上分');

            DB::transaction(function () use ($transactionUtil, $deposit, $request) {
                $updatedRow = Transaction::where([
                    'id'                          => $deposit->getKey(),
                    'to_wallet_settled'           => false,
                    'to_wallet_should_settled_at' => $deposit->to_wallet_should_settled_at,
                ])->update([
                    'to_wallet_should_settled_at' => $request->delay_settle_hours > 0 ? now()->addHours($request->delay_settle_hours) : null,
                ]);

                throw_if($updatedRow !== 1, new RaceConditionException());

                $transactionUtil->settleToWallet($deposit->refresh());
            });
        }

        if ($deposit->status === Transaction::STATUS_MANUAL_SUCCESS && $request->has('delay_settle_minutes')) {
            abort_if($deposit->to_wallet_settled, Response::HTTP_BAD_REQUEST, '充值已上分');

            DB::transaction(function () use ($transactionUtil, $deposit, $request) {
                $updatedRow = Transaction::where([
                    'id'                          => $deposit->getKey(),
                    'to_wallet_settled'           => false,
                    'to_wallet_should_settled_at' => $deposit->to_wallet_should_settled_at,
                ])->update([
                    'to_wallet_should_settled_at' => $request->delay_settle_minutes > 0 ? now()->addMinutes($request->delay_settle_minutes) : null,
                ]);

                throw_if($updatedRow !== 1, new RaceConditionException());

                $transactionUtil->settleToWallet($deposit->refresh());
            });
        }

        if ($request->status === Transaction::STATUS_PAYING) {
            abort_if(
                !$deposit->locked,
                Response::HTTP_BAD_REQUEST,
                __('transaction.You have to lock before doing this')
            );

            abort_if(
                $deposit->locked
                    && !$deposit->lockedBy->is(auth()->user()->realUser()),
                Response::HTTP_BAD_REQUEST,
                __('transaction.Already been locked, you are not allowing to do status update')
            );

            abort_if(
                !$deposit->success(),
                Response::HTTP_BAD_REQUEST,
                __('transaction.Current status is not available to be updated to target status')
            );

            abort_if(
                $deposit->to_wallet_settled,
                Response::HTTP_BAD_REQUEST,
                '已上分，无法取消'
            );

            $deposit = $transactionUtil->rollbackAsPaying($deposit, auth()->user()->realUser());
        }

        if (in_array($request->status, [Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_FAILED])) {
            // abort_if(
            //     !in_array($deposit->status, [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]),
            //     Response::HTTP_BAD_REQUEST,
            //     __('transaction.Current status is not available to be updated to target status')
            // );

            abort_if(
                !$deposit->locked,
                Response::HTTP_BAD_REQUEST,
                __('transaction.You have to lock before doing this')
            );

            abort_if(
                $deposit->locked
                    && !$deposit->lockedBy->is(auth()->user()->realUser()),
                Response::HTTP_BAD_REQUEST,
                __('transaction.Already been locked, you are not allowing to do status update')
            );

            switch ($request->status) {
                case Transaction::STATUS_MANUAL_SUCCESS:
                    DB::transaction(function () use ($transactionUtil, $deposit, $request) {
                        $updatedRow = ($deposit->type === Transaction::TYPE_NORMAL_DEPOSIT ? 1 : 0);
                        if ($request->has('delay_settle_hours')) {
                            $updatedRow = Transaction::where([
                                'id'                          => $deposit->getKey(),
                                'to_wallet_settled'           => false,
                                'to_wallet_should_settled_at' => $deposit->to_wallet_should_settled_at,
                            ])->update([
                                'to_wallet_should_settled_at' => $request->delay_settle_hours > 0 ? now()->addHours($request->delay_settle_hours) : null,
                                'deduct_frozen_balance' => $request->input('deduct_frozen_balance', false)
                            ]);
                        }

                        if ($request->has('delay_settle_minutes')) {
                            $updatedRow = Transaction::where([
                                'id'                          => $deposit->getKey(),
                                'to_wallet_settled'           => false,
                                'to_wallet_should_settled_at' => $deposit->to_wallet_should_settled_at,
                            ])->update([
                                'to_wallet_should_settled_at' => $request->delay_settle_minutes > 0 ? now()->addMinutes($request->delay_settle_minutes) : null,
                                'deduct_frozen_balance' => $request->input('deduct_frozen_balance', false)
                            ]);
                        }

                        throw_if($updatedRow !== 1, new RaceConditionException());

                        $transactionUtil->markAsSuccess($deposit->refresh(), auth()->user()->realUser());
                    });
                    break;
                case Transaction::STATUS_FAILED:
                    $transactionUtil->markAsFailed($deposit, auth()->user()->realUser());
                    break;
                default:
                    abort(Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        $transactionUtil->supportLockingLogics($deposit, $request);

        if ($request->to_id === 0 && isset($request->note)) {
            $deposit = $transactionUtil->markAsNormalWithdraw($deposit, $request->note);
        }

        return Deposit::make($deposit->load('to', 'transactionNotes.user'));
    }

    public function exportCsv(Request $request)
    {
        if ($request->status) {
            $request->merge(['status' => explode(',', $request->status)]);
        }
        if ($request->notify_status) {
            $request->merge(['type' => explode(',', $request->type)]);
        }

        $this->validate($request, [
            'started_at' => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'ended_at'   => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
            'status'     => ['nullable', 'array'],
            'type'       => [
                'nullable', 'int', Rule::in(Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT)
            ],
        ]);

        DateRangeValidator::parse($request)
            ->validateMonths(2)
            ->validateDays(31);

        $builder = new TransactionBuilder;
        $deposits = $builder->deposits($request)->get();

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
        $typeMap = [
            2 => '跑分充值',
            3 => '一般充值'
        ];

        return response()->streamDownload(
            function () use ($deposits, $statusTextMap, $typeMap) {
                $handle = fopen('php://output', 'w');
                fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // for UTF-8 BOM

                fputcsv($handle, [
                    '锁定人', '充值类型', '码商名称', '订单金额', '收款方资讯', '订单状态', '匹配时间', '建立时间', '成功时间',
                    '系统/商户订单号', '商户名称'
                ]);

                foreach ($deposits as $transaction) {
                    $account = $transaction->from_channel_account;
                    fputcsv($handle, [
                        optional($transaction->lockedBy)->username,
                        $typeMap[$transaction->type],
                        $transaction->to->name,
                        $transaction->amount,
                        "{$account['bank_card_holder_name']}-{$account['bank_name']}-{$account['bank_card_number']}",
                        data_get($statusTextMap, $transaction->status, '无'),
                        optional($transaction->last_matched_at)->toIso8601String(),
                        optional($transaction->created_at)->toIso8601String(),
                        optional($transaction->confirmed_at)->toIso8601String(),
                        $transaction->system_order_number,
                        $transaction->from->name
                    ]);
                }

                fclose($handle);
            },
            '充值报表' . now()->format('Ymd') . '.csv'
        );
    }
}

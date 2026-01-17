<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TransactionRewardCollection;
use App\Models\FeatureToggle;
use App\Models\MatchingDepositReward;
use App\Models\Permission;
use App\Models\TransactionReward;
use App\Utils\BCMathUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class TransactionRewardController extends Controller
{

    /**
     * @var BCMathUtil
     */
    private $bcMath;

    public function __construct(BCMathUtil $bcMath)
    {
        $this->middleware(['permission:'.Permission::ADMIN_MANAGE_TRANSACTION_REWARD]);
        $this->bcMath = $bcMath;
    }

    public function destroy(TransactionReward $transactionReward)
    {
        $transactionReward->delete();

        return response()->noContent();
    }

    public function index(Request $request)
    {
        $transactionRewards = TransactionReward::all()->groupBy('time_range')->map(function (Collection $value) use (
            $request
        ) {
            return TransactionRewardCollection::make($value->sort(function (TransactionReward $a, TransactionReward $b) {
                return bccomp($a->min_amount, $b->max_amount, 2);
            })->values())->toArray($request);
        })->sortKeys();

        return response()->json([
            'data' => $transactionRewards,
            'meta' => [
                'transaction_reward_feature' => FeatureToggle::findOrFail(FeatureToggle::TRANSACTION_REWARD),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'started_at'    => 'required',
            'ended_at'      => 'required',
            'amount'        => 'required|string',
            'reward_unit'   => [
                'required',
                Rule::in(TransactionReward::REWARD_UNIT_SINGLE, TransactionReward::REWARD_UNIT_PERCENT)
            ],
            'reward_amount' => 'required|numeric',
        ]);

        $amounts = $this->parseAmount($request->input('amount'));

        $this->abortIfConflict($request, $amounts);

        return \App\Http\Resources\Admin\TransactionReward::make(
            TransactionReward::create([
                'min_amount'    => $amounts['min_amount'],
                'max_amount'    => $amounts['max_amount'],
                'reward_amount' => $request->input('reward_amount'),
                'reward_unit'   => $request->input('reward_unit'),
                'started_at'    => $request->input('started_at'),
                'ended_at'      => $request->input('ended_at'),
            ])
        );
    }

    private function parseAmount(string $amount)
    {
        $amounts = explode('~', $amount);

        abort_if(
            count($amounts) !== 2,
            Response::HTTP_BAD_REQUEST,
            '格式错误，格式范例：100~200'
        );

        abort_if(
            $this->bcMath->ltZero($amounts[0])
            || $this->bcMath->lt($amounts[1], $amounts[0])
            || ($this->bcMath->eq($amounts[0], 0) && $this->bcMath->eq($amounts[1], 0)),
            Response::HTTP_BAD_REQUEST,
            '金额错误'
        );

        return [
            'min_amount' => trim($amounts[0]),
            'max_amount' => trim($amounts[1]),
        ];
    }

    private function abortIfConflict(Request $request, array $amounts, ?TransactionReward $except = null)
    {
        $startedAt = $except ? $except->started_at : $request->input('started_at');
        $endedAt = $except ? $except->ended_at : $request->input('ended_at');

        $startedAt = Carbon::make('1970-01-01')->setTimeFromTimeString($startedAt);
        $endedAt = Carbon::make('1970-01-01')->setTimeFromTimeString($endedAt);

        $transactionRewards = TransactionReward::where(function (Builder $builder) use ($startedAt, $endedAt) {
            $builder->where('started_at', '!=', $startedAt)->orWhere('ended_at', '!=', $endedAt);
        })
            ->when($except, function (Builder $builder, TransactionReward $except) {
                $builder->where('id', '!=', $except->getKey());
            })
            ->get()
            ->map(function (TransactionReward $transactionReward) {
                $transactionRewardStartedAt = Carbon::make('1970-01-01')->setTimeFromTimeString($transactionReward->started_at);
                $transactionRewardEndedAt = Carbon::make('1970-01-01')->setTimeFromTimeString($transactionReward->ended_at);

                if ($transactionRewardStartedAt->gt($transactionRewardEndedAt)) {
                    $transactionRewardEndedAt->addDay();
                }

                return [
                    'started_at' => $transactionRewardStartedAt,
                    'ended_at'   => $transactionRewardEndedAt,
                ];
            })
        ->filter(function (array $timeRange) use ($startedAt, $endedAt) {
            return $startedAt->betweenIncluded($timeRange['started_at'], $timeRange['ended_at']) || $endedAt->betweenIncluded($timeRange['started_at'], $timeRange['ended_at']);
        });

        abort_if(
            $transactionRewards->isNotEmpty(),
            Response::HTTP_BAD_REQUEST,
            '时间区间重叠'
        );

        abort_if(
            TransactionReward::where('started_at', $startedAt)
                ->where('ended_at', $endedAt)
                ->where('max_amount', '>=', $amounts['min_amount'])
                ->where('min_amount', '<=', $amounts['max_amount'])
                ->when($except, function (Builder $builder, TransactionReward $except) {
                    $builder->where('id', '!=', $except->getKey());
                })
                ->exists(),
            Response::HTTP_BAD_REQUEST,
            '金额区间重叠'
        );
    }

    public function update(Request $request, TransactionReward $transactionReward)
    {
        $this->validate($request, [
            'amount'        => 'string',
            'reward_unit'   => [
                Rule::in(MatchingDepositReward::REWARD_UNIT_SINGLE, MatchingDepositReward::REWARD_UNIT_PERCENT)
            ],
            'reward_amount' => 'numeric',
        ]);

        if ($request->filled('amount')) {
            $amounts = $this->parseAmount($request->input('amount'));

            $this->abortIfConflict($request, $amounts, $transactionReward);

            $transactionReward->fill($amounts);
        }

        foreach (['reward_amount', 'reward_unit'] as $attribute) {
            if ($request->filled($attribute)) {
                $transactionReward->$attribute = $request->input($attribute);
            }
        }

        $transactionReward->save();

        return \App\Http\Resources\Admin\TransactionReward::make($transactionReward);
    }
}

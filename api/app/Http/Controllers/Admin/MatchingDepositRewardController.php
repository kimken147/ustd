<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MatchingDepositRewardCollection;
use App\Model\FeatureToggle;
use App\Model\MatchingDepositReward;
use App\Model\Permission;
use App\Utils\BCMathUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class MatchingDepositRewardController extends Controller
{

    /**
     * @var BCMathUtil
     */
    private $bcMath;

    public function __construct(BCMathUtil $bcMath)
    {
        $this->middleware(['permission:'.Permission::ADMIN_MANAGE_MATCHING_DEPOSIT_REWARD]);
        $this->bcMath = $bcMath;
    }

    public function destroy(MatchingDepositReward $matchingDepositReward)
    {
        $matchingDepositReward->delete();

        return response()->noContent();
    }

    public function index()
    {
        return MatchingDepositRewardCollection::make(
            MatchingDepositReward::orderBy('min_amount')->orderBy('max_amount')->paginate(20)
        )->additional([
            'meta' => [
                'matching_deposit_reward_feature' => FeatureToggle::findOrFail(FeatureToggle::MATCHING_DEPOSIT_REWARD),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'amount'        => 'required|string',
            'reward_unit'   => [
                'required',
                Rule::in(MatchingDepositReward::REWARD_UNIT_SINGLE, MatchingDepositReward::REWARD_UNIT_PERCENT)
            ],
            'reward_amount' => 'required|numeric',
        ]);

        $amounts = $this->parseAmount($request->input('amount'));

        $this->abortIfAmountConflict($amounts['min_amount'], $amounts['max_amount']);

        return \App\Http\Resources\Admin\MatchingDepositReward::make(
            MatchingDepositReward::create([
                'min_amount'    => $amounts['min_amount'],
                'max_amount'    => $amounts['max_amount'],
                'reward_amount' => $request->input('reward_amount'),
                'reward_unit'   => $request->input('reward_unit'),
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

    private function abortIfAmountConflict($minAmount, $maxAmount, ?MatchingDepositReward $except = null)
    {
        abort_if(
            MatchingDepositReward::where('max_amount', '>=', $minAmount)
                ->where('min_amount', '<=', $maxAmount)
                ->when($except, function (Builder $builder, MatchingDepositReward $except) {
                    $builder->where('id', '!=', $except->getKey());
                })
                ->exists(),
            Response::HTTP_BAD_REQUEST,
            '金额区间重复'
        );
    }

    public function update(Request $request, MatchingDepositReward $matchingDepositReward)
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

            $this->abortIfAmountConflict($amounts['min_amount'], $amounts['max_amount'], $matchingDepositReward);

            $matchingDepositReward->fill($amounts);
        }

        foreach (['reward_amount', 'reward_unit'] as $attribute) {
            if ($request->filled($attribute)) {
                $matchingDepositReward->$attribute = $request->input($attribute);
            }
        }

        $matchingDepositReward->save();

        return \App\Http\Resources\Admin\MatchingDepositReward::make($matchingDepositReward);
    }
}

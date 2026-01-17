<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MerchantThirdChannelsCollection;
use App\Http\Resources\Admin\MerchantThirdChannels;
use App\Model\Permission;
use App\Model\Transaction;
use App\Model\ThirdChannel;
use App\Model\MerchantThirdChannel;
use App\Model\User;
use App\Model\UserChannelAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MerchantThirdChannelController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:'.Permission::ADMIN_MANAGE_MERCHANT_THIRD_CHANNEL])->except(['index']);
    }

    public function destroy($id)
    {
        /** @var TransactionGroup $transactionGroup */
        $merchantThirdChannel = MerchantThirdChannel::findOrFail($id);

        DB::transaction(function () use ($merchantThirdChannel) {
            $merchantThirdChannel->delete();
        });

        return response()->noContent();
    }

    public function index(Request $request)
    {
        $users = User::where('role', User::ROLE_MERCHANT)->with('thirdChannels.thirdChannel.channel');

        $filterNameOrUsername = User::where(function (Builder $users) use ($request) {
            $users->where('name', 'like', "%{$request->input('name_or_username')}%")
                ->orWhere('username', $request->input('name_or_username'));
        })->select('id');

        $users->when($request->filled('name_or_username'), function (Builder $users) use ($filterNameOrUsername) {
            $users->where(function (Builder $users) use ($filterNameOrUsername) {
                $users->whereIn('id', $filterNameOrUsername)
                    ->orWhereHas('thirdChannels.thirdChannel',
                        function (Builder $thirdchannel) use ($filterNameOrUsername) {
                            $thirdchannel->whereIn('id', $filterNameOrUsername);
                        });
            });
        });

        $users->when($request->filled('merchant_id'), function (Builder $users) use ($request) {
            $users->where(function (Builder $users) use ($request) {
                $users->where('id', $request->input('merchant_id'))
                    ->orWhereHas('thirdChannels.thirdChannel',
                        function (Builder $thirdchannel) use ($request) {
                            $thirdchannel->where('id', $request->input('merchant_id'));
                        });
            });
        });

        $users->when($request->filled('thirdchannel_name'), function (Builder $users) use ($request) {
            $users->where(function (Builder $users) use ($request) {
                $users->whereHas('thirdChannels.thirdChannel',
                    function (Builder $thirdchannel) use ($request) {
                        $thirdchannel->where('name', 'like', "%{$request->input('thirdchannel_name')}%");
                    });
            });
        });

        $users->when($request->filled('channel_code'), function (Builder $users) use ($request) {
            $users->where(function (Builder $users) use ($request) {
                $users->whereHas('thirdChannels.thirdChannel',
                    function (Builder $thirdchannel) use ($request) {
                        $thirdchannel->whereIn('channel_code', $request->input('channel_code'));
                    });
            });
        });

        $results = $users->paginate(20);
        $results->each(function ($result) use ($request) {
            if ($request->has('status')) {
                $status = $request->input('status');
                $result->thirdChannels = $result->thirdChannels->filter(function ($userThirdChannel) use ($status) {
                    return $userThirdChannel->thirdChannel->status == $status;
                });
            }
        });

        return MerchantThirdChannelsCollection::make($results);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'merchant_id' => 'required|int',
            'thirdchannel_id' => 'required|int',
            'deposit_fee_percent' => 'required',
            'withdraw_fee' => 'required',
            'deposit_min' => 'required',
            'deposit_max' => 'required',
            'daifu_min' => 'required',
            'daifu_max' => 'required',
        ]);

        abort_if(
            !User::where('role', User::ROLE_MERCHANT)->where('id', $request->input('merchant_id'))->exists(),
            Response::HTTP_BAD_REQUEST,
            '查无商户'
        );

        abort_if(
            !ThirdChannel::where('id', $request->input('thirdchannel_id'))->exists(),
            Response::HTTP_BAD_REQUEST,
            '无此通道'
        );

        abort_if(
            MerchantThirdChannel::where([
                'owner_id' => $request->input('merchant_id'),
                'thirdchannel_id' => $request->input('thirdchannel_id'),
                'deposit_min' => $request->input('deposit_min'),
                'deposit_max' => $request->input('deposit_max'),
                'daifu_min' => $request->input('daifu_min'),
                'daifu_max' => $request->input('daifu_max'),
            ])->exists(),
            Response::HTTP_BAD_REQUEST,
            '通道重复'
        );

        DB::transaction(function () use ($request) {
            /** @var TransactionGroup $transactionGroup */
            $merchantThirdChannel = MerchantThirdChannel::create([
                'owner_id'            => $request->input('merchant_id'),
                'thirdchannel_id'     => $request->input('thirdchannel_id'),
                'deposit_fee_percent' => $request->input('deposit_fee_percent'),
                'withdraw_fee'        => $request->input('withdraw_fee'),
                'daifu_fee_percent'   => $request->daifu_fee_percent ? $request->input('daifu_fee_percent') : 0, // 之後菲律賓需要用到 代付手續費再設定
                'deposit_min' => $request->input('deposit_min'),
                'deposit_max' => $request->input('deposit_max'),
                'daifu_min'   => $request->input('daifu_min'),
                'daifu_max'   => $request->input('daifu_max'),
            ]);
        });

        return response()->json(null, Response::HTTP_CREATED);
    }

    public function update(Request $request, MerchantThirdChannel $merchantThirdChannel)
    {
        $this->validate($request, [
            'deposit_fee_percent' => 'required',
            'withdraw_fee' => 'required',
            'daifu_fee_percent' => 'required',
            'deposit_min' => 'required',
            'deposit_max' => 'required',
            'daifu_min' => 'required',
            'daifu_max' => 'required',
        ]);

        $merchantThirdChannel->update($request->only('deposit_fee_percent', 'withdraw_fee', 'daifu_fee_percent', 'deposit_min', 'deposit_max', 'daifu_min', 'daifu_max'));

        return response()->noContent();
    }
}

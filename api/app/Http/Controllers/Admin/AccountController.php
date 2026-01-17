<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Controllers\Controller;

use App\Channel\Resources\UserChannelAccount as UserChannelAccountResource;

class AccountController extends Controller
{
    public function index(Request $request, $channel)
    {
        $operator = \Auth::user();
        // 權限判斷

        return UserChannelAccountResource::collection($channel->getAccounts());
    }

    public function show(Request $request, $channel, $account)
    {
        $operator = \Auth::user();
        // 權限判斷

        $account = $channel->getAccounts()->where('id', $account)->first();

        return UserChannelAccountResource::make($account);
    }

    public function update(Request $request, $channel, $account)
    {
        $operator = \Auth::user();
        // 權限判斷

        $data = $request->all();

        try {
            $account = $channel->updateAccount($account, $data);

            return new UserChannelAccountResource($account);
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }
    }

    public function verify()
    {

    }

    public function statistic($request, $id)
    {

    }
}

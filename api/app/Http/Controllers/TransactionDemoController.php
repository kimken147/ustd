<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\User;
use App\Models\UserChannel;
use App\Http\Controllers\ThirdParty\UserChannelMatching;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionDemoController extends Controller
{
    use UserChannelMatching;

    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function __invoke(Request $request)
    {
        if (env('APP_ENV') && env('APP_ENV') != 'local') {
            URL::forceScheme('https');
        }

        if (0 === strcasecmp($request->method(), 'get')) {
            return view('v1.transaction-demo');
        }

        Log::debug(self::class, $request->all());

        $this->validate($request, [
            'channel_code' => 'required',
            'username'     => 'required',
            'secret_key'   => 'required',
            'amount'       => 'required|numeric|min:1',
            'notify_url'   => 'required',
            'order_number' => 'required',
        ]);

        $merchant = User::where('username', $request->username)
            ->where('role', User::ROLE_MERCHANT)
            ->where('secret_key', $request->secret_key)
            ->first();

        if (!$merchant) {
            return $this->back(['username' => '商户号错误']);
        }

        /** @var Channel|null $channel */
        $channel = Channel::where('code', $request->channel_code)->firstOrFail();

        /** @var UserChannel $userChannel */
        [$userChannel, $channelAmount] = $this->findSuitableUserChannel($merchant, $channel, $request->amount);

        if (!$userChannel) {
            return $this->back(['channel_code' => '商户未配置该通道']);
        }

        if ($userChannel->isDisabled()) {
            return $this->back(['channel_code' => '该通道未启用']);
        }

        return redirect()->route(
            'api.v1.create-transactions',
            $this->withSign(
                collect($request->only([
                    'channel_code',
                    'username',
                    'amount',
                    'notify_url',
                    'return_url',
                    'order_number',
                    'real_name',
                    'client_ip',
                    'usdt_rate',
                    'bank_name',
                    'match_last_account'
                ]))
                , $merchant
            )->toArray()
        );
    }

    private function back($errors)
    {
        return redirect()
            ->route('v1.transaction-demo')
            ->withInput()
            ->withErrors($errors);
    }

    private function withSign(Collection $postData, User $merchant)
    {
        $postData = $postData->sortKeys();

        return $postData->merge([
            'sign' => strtolower(md5(urldecode(http_build_query(array_filter($postData->toArray())).'&secret_key='.$merchant->secret_key)))
        ])->forget('secret_key');
    }
}

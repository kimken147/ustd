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
use Illuminate\Support\Facades\Http;

class WithdrawDemoController extends Controller
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
            return view('v1.withdraw-demo');
        }

        Log::debug(self::class, $request->all());

        $merchant = User::where('username', $request->username)
            ->where('role', User::ROLE_MERCHANT)
            ->where('secret_key', $request->secret_key)
            ->first();

        if (!$merchant) {
            return $this->back(['username' => '商户号错误']);
        }

        if (!$merchant->withdraw_enable) {
            return $this->back(['withdraw_enable' => '下发未启用']);
        }

        $data = array_filter($request->only([
            'username',
            'amount',
            'notify_url',
            'order_number',
            'bank_name',
            'bank_province',
            'bank_city',
            'bank_card_holder_name',
            'bank_card_number',
            'usdt_rate'
        ]));
        $postData = $this->withSign(
            collect($data)
            , $merchant
        )->toArray();

        $response = Http::post(route('withdraws.store'), $postData);

        return $response->json();
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

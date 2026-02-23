<?php

namespace App\Http\Controllers\ThirdParty;

use App\Models\User;
use App\Models\Channel;
use App\Models\Transaction;
use App\Repository\FeatureToggleRepository;
use Illuminate\Support\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

trait MatchedJsonResponse
{
    private function responseOf(
        Transaction             $transaction,
        FeatureToggleRepository $featureToggleRepository
    )
    {
        if (env("APP_ENV") != "local") {
            URL::forceScheme("https");
        }
        $channel = $transaction->channel;

        $merchant = User::firstWhere("username", request()->username);

        $cashierUrl = urldecode(
            route("api.v1.cashier", $transaction->system_order_number)
        );

        $noteEnable = $transaction->channel->note_enable;

        $info = [
            "casher_url" => $cashierUrl,
            "note" => $noteEnable ? $transaction->note : "",
        ];

        // USDT 通道回傳錢包地址和鏈網絡
        if ($channel->code === Channel::CODE_USDT) {
            $info['wallet_address'] =
                data_get($transaction->from_channel_account, 'wallet_address', '');
            $info['chain_network'] =
                data_get($transaction->from_channel_account, 'chain_network', '');
        }

        return \App\Http\Resources\ThirdParty\Transaction::make($transaction)
            ->withMatchedInformation($info)
            ->additional([
                "http_status_code" => Response::HTTP_CREATED,
                "message" => "匹配成功",
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    private function withSign(Collection $postData, User $merchant)
    {
        $postData = $postData->sortKeys();

        return $postData
            ->merge([
                "sign" => strtolower(
                    md5(
                        urldecode(
                            http_build_query(
                                array_filter($postData->toArray())
                            ) .
                            "&secret_key=" .
                            $merchant->secret_key
                        )
                    )
                ),
            ])
            ->forget("secret_key");
    }
}

<?php

namespace App\Http\Controllers\ThirdParty;

use App\Model\User;
use App\Model\Channel;
use App\Model\FeatureToggle;
use App\Model\Transaction;
use App\Model\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use App\Utils\ThirdPartyResponseUtil;
use Illuminate\Support\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            // URL::forceRootUrl(env('APP_URL'));
        }
        $channel = $transaction->channel;

        $merchant = User::firstWhere("username", request()->username);

        $country = $transaction->channel->country;

        $noteEnable = $transaction->channel->note_enable;
        $mayaCashierUrl =
            env("ADMIN_URL") . "/maya/paying/" . $transaction->order_number;
        $cashierUrl =
            $transaction->channel_code == Channel::CODE_MAYA
                ? $mayaCashierUrl
                : urldecode(
                route("api.v1.cashier", $transaction->system_order_number)
            );

        $info = [
            "casher_url" => $cashierUrl,
            "note" => $noteEnable ? $transaction->note : "",
        ];

        if (Str::startsWith($channel->code, ["QR_"]) || in_array($channel->code, [
                Channel::CODE_ALIPAY_BAC,
                Channel::CODE_ALIPAY_SAC,
                Channel::CODE_ALIPAY_COPY,
                Channel::CODE_ALIPAY_GC,
                Channel::CODE_WECHATPAY_SAC,
                Channel::CODE_WECHATPAY_BAC])) {
            $disableShowingAccount = $featureToggleRepository->enabled(
                FeatureToggle::DISABLE_SHOWING_ACCOUNT_ON_QR_ALIPAY_MATCHED_PAGE
            );
            $disableShowingQrCode = $featureToggleRepository->enabled(
                FeatureToggle::DISABLE_SHOWING_QR_CODE_ON_QR_ALIPAY_MATCHED_PAGE
            );

            $info["receiver_account"] = $disableShowingAccount
                ? ""
                : data_get($transaction, "from_channel_account.account");
            $info["receiver_name"] = $disableShowingAccount
                ? ""
                : data_get($transaction, "from_channel_account.receiver_name");
            $info["qrcode_url"] = ($transaction->channel_code == Channel::CODE_QR_ALIPAY ||
                $channel->code === Channel::CODE_ALIPAY_BAC ||
                $channel->code === Channel::CODE_ALIPAY_SAC ||
                $channel->code === Channel::CODE_ALIPAY_COPY ||
                $channel->code === Channel::CODE_ALIPAY_GC) && $disableShowingQrCode
                ? ""
                : $this->qrCodeS3Path($transaction);
            $info["scheme_url"] = data_get(
                $transaction->from_channel_account,
                UserChannelAccount::DETAIL_KEY_REDIRECT_URL
            );
        }

        if ($channel->code == Channel::CODE_BANK_CARD) {
            $info["receiver_account"] =
                $transaction->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER] ?? "";
            $info["receiver_name"] =
                $transaction->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME] ?? "";
            $info["receiver_bank_name"] =
                $transaction->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_NAME] ?? "";
            $info["receiver_bank_branch"] =
                $transaction->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_CARD_BRANCH] ?? "";
        }

        if ($channel->code == Channel::CODE_USDT) {
            $info["wallet_address"] =
                $transaction->from_channel_account["account"] ?? "";
            $info["usdt_rate"] = $transaction->usdt_rate;
            $info["rate_amount"] = $transaction->rate_amount;
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

    private function qrCodeS3Path(Transaction $transaction)
    {
        $qrCodeFilePath = data_get(
            $transaction,
            "from_channel_account." .
            UserChannelAccount::DETAIL_KEY_PROCESSED_QR_CODE_FILE_PATH,
            "404.jpg"
        );

        return Storage::disk("user-channel-accounts-qr-code")->temporaryUrl(
            $qrCodeFilePath,
            now()->addHour()
        );
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

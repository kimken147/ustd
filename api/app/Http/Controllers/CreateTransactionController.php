<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\CreateTransactionService;
use App\Services\Transaction\DTO\CreateTransactionContext;
use App\Services\Transaction\DTO\CreateTransactionResult;
use App\Services\Transaction\Exceptions\TransactionValidationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class CreateTransactionController extends Controller
{
    public function __construct(
        private CreateTransactionService $service,
        private FeatureToggleRepository $featureToggleRepository
    ) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        if (env("APP_ENV") != "local") {
            URL::forceScheme("https");
        }

        $this->validate($request, [
            "channel_code" => "required",
            "username" => "required",
            "amount" => "required",
        ]);

        try {
            $context = CreateTransactionContext::fromViewRequest($request);
            $result = $this->service->create($context);

            return $this->renderView($result);
        } catch (TransactionValidationException $e) {
            return $this->errorView($e->getMessage());
        }
    }

    private function renderView(CreateTransactionResult $result): View|RedirectResponse
    {
        $transaction = $result->transaction;
        $channel = $transaction->channel;
        $country = $channel->country;

        return match ($result->status) {
            'matched' => $this->matchedView($transaction),
            'third_paying' => $this->thirdPayingView($result),
            'matching' => $this->matchingView($transaction),
            'matching_timed_out' => view("v1.transactions.{$country}.matching-timed-out", compact('transaction')),
            'paying_timed_out' => view("v1.transactions.{$country}.paying-timed-out", compact('transaction')),
            'success' => view("v1.transactions.{$country}.paying-success", compact('transaction')),
            default => view("v1.transactions.{$country}.please-try-later", compact('transaction')),
        };
    }

    private function matchedView(Transaction $transaction): View
    {
        $channel = $transaction->channel;
        $country = $channel->country;
        $code = $this->getChannelCode($channel, $transaction);
        $path = $this->buildMatchedViewPath($channel, $country, $code);

        if (!view()->exists($path)) {
            abort(Response::HTTP_NOT_FOUND, "通道不存在");
        }

        $fromChannelAccount = $transaction->from_channel_account;

        return view($path, [
            "channel" => $channel,
            "disableShowingAccount" => $this->featureToggleRepository->enabled(
                FeatureToggle::DISABLE_SHOWING_ACCOUNT_ON_QR_ALIPAY_MATCHED_PAGE
            ),
            "disableShowingQrCode" => $this->featureToggleRepository->enabled(
                FeatureToggle::DISABLE_SHOWING_QR_CODE_ON_QR_ALIPAY_MATCHED_PAGE
            ),
            "transaction" => $transaction,
            "qrCodePath" => $this->qrCodeS3Path($transaction),
            "note" => $transaction->note,
            "payingLimitEnabled" => $channel->transaction_timeout_enable,
            "payingLimitSeconds" => $channel->transaction_timeout,
            "redirectUrl" => data_get(
                $fromChannelAccount,
                UserChannelAccount::DETAIL_KEY_REDIRECT_URL,
                $channel->scanQrcodeUrlScheme()
            ),
            "bankName" => data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_NAME),
            "bankBranch" => data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_CARD_BRANCH),
            "bankCardHolderName" => data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME),
            "bankCardNumber" => data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER),
            "apiHost" => env("APP_URL"),
            "code" => $code
        ]);
    }

    private function thirdPayingView(CreateTransactionResult $result): View|RedirectResponse
    {
        $transaction = $result->transaction;
        $channel = $transaction->channel;
        $country = $channel->country;

        // 如果四方使用自己的收銀台，重定向到四方 URL
        $thirdChannel = $transaction->thirdChannel;
        if ($thirdChannel && $thirdChannel->cashier_mode != 2 && $result->cashierUrl) {
            return redirect($result->cashierUrl);
        }

        // 使用本站收銀台顯示四方返回的資訊
        $code = $this->getChannelCode($channel, $transaction);
        $path = $this->buildMatchedViewPath($channel, $country, $code);

        if (!view()->exists($path)) {
            abort(Response::HTTP_NOT_FOUND, "通道不存在");
        }

        $toChannelAccount = $transaction->to_channel_account;

        return view($path, [
            "channel" => $channel,
            "disableShowingAccount" => $this->featureToggleRepository->enabled(
                FeatureToggle::DISABLE_SHOWING_ACCOUNT_ON_QR_ALIPAY_MATCHED_PAGE
            ),
            "disableShowingQrCode" => $this->featureToggleRepository->enabled(
                FeatureToggle::DISABLE_SHOWING_QR_CODE_ON_QR_ALIPAY_MATCHED_PAGE
            ),
            "transaction" => $transaction,
            "qrCodePath" => "",
            "note" => $transaction->note,
            "payingLimitEnabled" => $channel->transaction_timeout_enable,
            "payingLimitSeconds" => $channel->transaction_timeout,
            "redirectUrl" => $channel->scanQrcodeUrlScheme(),
            "bankName" => $toChannelAccount['receiver_bank_name'] ?? '',
            "bankBranch" => $toChannelAccount['receiver_bank_branch'] ?? '',
            "bankCardHolderName" => $toChannelAccount['receiver_name'] ?? '',
            "bankCardNumber" => $toChannelAccount['receiver_account'] ?? '',
            "apiHost" => env("APP_URL"),
            "code" => $code
        ]);
    }

    private function matchingView(Transaction $transaction): View
    {
        $channel = $transaction->channel;
        $country = $channel->country;
        $code = $this->getChannelCode($channel, $transaction);
        $version = $channel->cashier_version;

        if ($version && view()->exists("v1.transactions.{$country}.{$code}.{$version}.matching")) {
            return view("v1.transactions.{$country}.{$code}.{$version}.matching", compact("transaction"));
        }

        if (view()->exists("v1.transactions.{$country}.{$code}.matching")) {
            return view("v1.transactions.{$country}.{$code}.matching", compact("transaction"));
        }

        abort(Response::HTTP_NOT_FOUND, "通道不存在");
    }

    private function errorView(string $errorMessage): View
    {
        return view("v1.transactions.error", compact("errorMessage"));
    }

    private function getChannelCode(Channel $channel, Transaction $transaction): string
    {
        $code = strtolower($channel->code);

        if ($channel->code == Channel::CODE_DC_BANK) {
            $bank = $transaction->to_channel_account["bank_name"] ?? '';
            return "dc_" . strtolower($bank);
        }

        if (in_array($channel->code, [
            Channel::CODE_ALIPAY_BAC,
            Channel::CODE_ALIPAY_SAC,
            Channel::CODE_ALIPAY_COPY,
            Channel::CODE_ALIPAY_GC
        ])) {
            return strtolower(Channel::CODE_QR_ALIPAY);
        }

        if (in_array($channel->code, [Channel::CODE_WECHATPAY_BAC, Channel::CODE_WECHATPAY_SAC])) {
            return strtolower(Channel::CODE_QR_WECHATPAY);
        }

        return $code;
    }

    private function buildMatchedViewPath(Channel $channel, string $country, string $code): string
    {
        $path = "v1.transactions.{$country}.{$code}";

        if ($channel->cashier_version != "") {
            $path = "{$path}.{$channel->cashier_version}";
        }

        return "{$path}.matched";
    }

    private function qrCodeS3Path(Transaction $transaction): string
    {
        $qrCodeFilePath = data_get(
            $transaction,
            "from_channel_account." . UserChannelAccount::DETAIL_KEY_PROCESSED_QR_CODE_FILE_PATH,
            "404.jpg"
        );

        try {
            return Storage::disk("user-channel-accounts-qr-code")->temporaryUrl(
                $qrCodeFilePath,
                now()->addHour()
            );
        } catch (\Exception $e) {
            return "";
        }
    }
}

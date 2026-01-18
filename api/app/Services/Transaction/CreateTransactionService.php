<?php

namespace App\Services\Transaction;

use App\Jobs\MarkPaufenTransactionMatchingTimedOut;
use App\Jobs\MarkPaufenTransactionPayingTimedOut;
use App\Models\BannedIp;
use App\Models\BannedRealname;
use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\FeatureToggle;
use App\Models\MerchantThirdChannel;
use App\Models\ThirdChannel;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\TransactionNote;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Notifications\MatchedTimeout;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\DTO\CallbackResult;
use App\Services\Transaction\DTO\CreateTransactionContext;
use App\Services\Transaction\DTO\CreateTransactionResult;
use App\Services\Transaction\DTO\DemoContext;
use App\Services\Transaction\DTO\DemoResult;
use App\Services\Transaction\DTO\MatchedInfo;
use App\Services\Transaction\Exceptions\TransactionValidationException;
use App\Services\Withdraw\DTO\ThirdPartyErrorResponse;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\TransactionFactory;
use App\Utils\TransactionNoteUtil;
use App\Utils\TransactionUtil;
use App\Utils\UsdtUtil;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Stevebauman\Location\Facades\Location;

class CreateTransactionService
{
    public function __construct(
        private BCMathUtil $bcMath,
        private FeatureToggleRepository $featureToggleRepository,
        private NotificationUtil $notificationUtil,
        private TransactionNoteUtil $transactionNoteUtil,
        private TransactionFactory $transactionFactory,
        private WalletUtil $walletUtil,
        private WhitelistedIpManager $whitelistedIpManager,
    ) {}

    /**
     * 建立交易（主入口）
     *
     * @throws TransactionValidationException
     */
    public function create(CreateTransactionContext $context): CreateTransactionResult
    {
        $channel = $this->validateAndGetChannel($context);
        $merchant = $this->validateAndGetMerchant($context, $channel);
        [$merchantUserChannel, $channelAmount] = $this->validateAndGetUserChannel($context, $merchant, $channel);

        $transaction = $this->findOrCreateTransaction($context, $merchant, $channel, $merchantUserChannel);

        // 處理各種狀態
        if ($transaction->paying() || $transaction->thirdChannelPaying()) {
            return $this->buildResult($transaction);
        }

        // 檢查匹配超時
        if ($transaction->matching() && $transaction->shouldMatchingTimedOut()) {
            $this->markAsMatchingTimedOut($transaction);
            return CreateTransactionResult::matchingTimedOut($transaction);
        }

        if ($transaction->success()) {
            return CreateTransactionResult::success($transaction);
        }

        if ($transaction->matchingTimedOut()) {
            return CreateTransactionResult::matchingTimedOut($transaction);
        }

        if ($transaction->payingTimedOut()) {
            return CreateTransactionResult::payingTimedOut($transaction);
        }

        if (!$transaction->matching()) {
            return $this->buildResult($transaction);
        }

        // 嘗試匹配
        return $this->attemptMatching($context, $transaction, $merchant, $channel, $merchantUserChannel, $channelAmount);
    }

    /**
     * 處理四方回調
     */
    public function handleCallback(string $orderNumber, Request $request): CallbackResult
    {
        $transactionUtil = app(TransactionUtil::class);

        $transaction = Transaction::where('order_number', $orderNumber)
            ->whereIn('type', [
                Transaction::TYPE_PAUFEN_TRANSACTION,
                Transaction::TYPE_NORMAL_WITHDRAW,
            ])
            ->first();

        if (!$transaction) {
            $transaction = Transaction::where('system_order_number', $orderNumber)
                ->whereIn('type', [
                    Transaction::TYPE_PAUFEN_TRANSACTION,
                    Transaction::TYPE_NORMAL_WITHDRAW,
                ])
                ->firstOrFail();
        }

        Log::debug(self::class . " callback", ["input" => $request->all()]);

        // 測試訂單直接返回成功
        if (
            !$transaction->thirdchannel_id &&
            (str_contains($transaction->order_number, "test") || str_contains($transaction->system_order_number, "test"))
        ) {
            return CallbackResult::success("success");
        }

        $thirdChannel = ThirdChannel::where("id", $transaction->thirdchannel_id)->firstOrFail();
        $path = "App\ThirdChannel\\{$thirdChannel->class}";
        $api = new $path($transaction->channel_code);

        if (
            in_array($transaction->status, [
                Transaction::STATUS_THIRD_PAYING,
                Transaction::STATUS_RECEIVED,
            ])
        ) {
            if ($thirdChannel->white_ip) {
                $allowIP = explode(",", $thirdChannel->white_ip);
                if (!in_array(Arr::last($request->ips()), $allowIP)) {
                    return CallbackResult::fail(__('common.IP not whitelisted'));
                }
            }

            $returnCallback = $api->callback($request, $transaction, $thirdChannel);

            Log::debug($path . " callback", ["result" => $returnCallback]);

            if (isset($returnCallback["success"])) {
                $transactionUtil->markAsSuccess($transaction, null, true, false, false);
                $responseBody = isset($returnCallback["resBody"])
                    ? json_encode($returnCallback["resBody"])
                    : ($api->success ?? "SUCCESS");
                return CallbackResult::success($responseBody);
            }

            if (isset($returnCallback["fail"])) {
                if ($errorMessage = $returnCallback["msg"] ?? null) {
                    TransactionNote::create([
                        "user_id" => 0,
                        "transaction_id" => $transaction->id,
                        "note" => "{$thirdChannel->name}: {$errorMessage}"
                    ]);
                }
                $transactionUtil->markAsFailed($transaction, null, $returnCallback["fail"], false);

                if (isset($returnCallback["resBody"])) {
                    $statusCode = $returnCallback["statusCode"] ?? 400;
                    return CallbackResult::fail(json_encode($returnCallback["resBody"]), $statusCode);
                }
                return CallbackResult::success($api->success ?? "SUCCESS");
            }

            return CallbackResult::fail($returnCallback["error"] ?? 'Unknown error');
        }

        if (isset($returnCallback["resBody"])) {
            return CallbackResult::success(json_encode($returnCallback["resBody"]));
        }
        return CallbackResult::success($api->success ?? "SUCCESS");
    }

    /**
     * 驗證並生成提單 URL（供 demo 使用）
     *
     * @throws TransactionValidationException
     */
    public function validateAndGenerateUrl(DemoContext $context): DemoResult
    {
        $merchant = User::where('username', $context->username)
            ->where('role', User::ROLE_MERCHANT)
            ->where('secret_key', $context->secretKey)
            ->first();

        if (!$merchant) {
            throw new TransactionValidationException(
                ThirdPartyErrorResponse::badRequest(400, __('common.Merchant number error'))
            );
        }

        $channel = Channel::where('code', $context->channelCode)->first();
        if (!$channel) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::channelNotFound());
        }

        [$userChannel, $channelAmount] = $this->findSuitableUserChannel($merchant, $channel, $context->amount);

        if (!$userChannel) {
            throw new TransactionValidationException(
                ThirdPartyErrorResponse::badRequest(400, __('common.Merchant channel not configured'))
            );
        }

        if ($userChannel->isDisabled()) {
            throw new TransactionValidationException(
                ThirdPartyErrorResponse::badRequest(400, __('common.Channel not enabled'))
            );
        }

        $postData = collect([
            'channel_code' => $context->channelCode,
            'username' => $context->username,
            'amount' => $context->amount,
            'notify_url' => $context->notifyUrl,
            'return_url' => $context->returnUrl,
            'order_number' => $context->orderNumber,
            'real_name' => $context->realName,
            'client_ip' => $context->clientIp,
            'usdt_rate' => $context->usdtRate,
            'bank_name' => $context->bankName,
            'match_last_account' => $context->matchLastAccount,
        ])->filter();

        $url = route('api.v1.create-transactions', $this->withSign($postData, $merchant)->toArray());

        return new DemoResult($url);
    }

    // ─── 驗證方法 ───

    /**
     * @throws TransactionValidationException
     */
    private function validateAndGetChannel(CreateTransactionContext $context): Channel
    {
        $channel = Channel::where("code", $context->channelCode)->first();

        if (!$channel) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::channelNotFound());
        }

        if ($channel->status !== Channel::STATUS_ENABLE) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::channelMaintenance());
        }

        // DC_BANK 需要 bank_name
        if ($channel->code == Channel::CODE_DC_BANK && !$context->bankName) {
            throw new TransactionValidationException(
                ThirdPartyErrorResponse::missingParameter('bank_name')
            );
        }

        return $channel;
    }

    /**
     * @throws TransactionValidationException
     */
    private function validateAndGetMerchant(CreateTransactionContext $context, Channel $channel): User
    {
        $clientIp = $context->clientIp ?? $this->whitelistedIpManager->extractIpFromRequest(request());

        // 檢查 IP 封禁
        if (BannedIp::where([
            "ipv4" => ip2long($clientIp),
            "type" => BannedIp::TYPE_TRANSACTION,
        ])->exists()) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::ipBanned());
        }

        // 檢查真實姓名封禁
        if ($context->realName && BannedRealname::where([
            "realname" => $context->realName,
            "type" => BannedRealname::TYPE_TRANSACTION,
        ])->exists()) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::realnameBanned());
        }

        $merchant = User::where([
            ["username", $context->username],
            ["role", User::ROLE_MERCHANT],
        ])->first();

        if (!$merchant) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::userNotFound());
        }

        if ($merchant->disabled()) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::userDisabled());
        }

        // 驗證簽名
        $this->validateSignature($context, $merchant);

        // 檢查 IP 白名單（僅四方 API）
        if ($context->isThirdParty) {
            if ($this->whitelistedIpManager->isNotAllowedToUseThirdPartyApi($merchant, request())) {
                throw new TransactionValidationException(ThirdPartyErrorResponse::invalidIp());
            }
        }

        if (!$merchant->transaction_enable) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::transactionDisabled());
        }

        // 檢查餘額限制
        $totalCost = $this->bcMath->sum([
            $context->amount,
            $merchant->wallet->available_balance,
        ]);

        if (
            $merchant->balance_limit >= 1 &&
            $this->bcMath->lt($merchant->balance_limit, $totalCost)
        ) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::balanceLimitExceeded());
        }

        return $merchant;
    }

    /**
     * @throws TransactionValidationException
     */
    private function validateSignature(CreateTransactionContext $context, User $merchant): void
    {
        $params = [
            'channel_code' => $context->channelCode,
            'username' => $context->username,
            'amount' => $context->amount,
            'order_number' => $context->orderNumber,
            'notify_url' => $context->notifyUrl,
        ];

        if ($context->clientIp) {
            $params['client_ip'] = $context->clientIp;
        }
        if ($context->realName) {
            $params['real_name'] = $context->realName;
        }
        if ($context->returnUrl) {
            $params['return_url'] = $context->returnUrl;
        }
        if ($context->bankName) {
            $params['bank_name'] = $context->bankName;
        }
        if ($context->usdtRate) {
            $params['usdt_rate'] = $context->usdtRate;
        }
        if ($context->matchLastAccount !== null) {
            $params['match_last_account'] = $context->matchLastAccount;
        }

        ksort($params);

        $sign = md5(
            urldecode(
                http_build_query($params) . "&secret_key=" . $merchant->secret_key
            )
        );

        // 也嘗試不含 real_name 的簽名（向後兼容）
        unset($params["real_name"]);
        ksort($params);
        $noRealNameSign = md5(
            urldecode(
                http_build_query($params) . "&secret_key=" . $merchant->secret_key
            )
        );

        if (!in_array(strtolower($context->sign), [$sign, $noRealNameSign])) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::invalidSign());
        }
    }

    /**
     * @throws TransactionValidationException
     */
    private function validateAndGetUserChannel(
        CreateTransactionContext $context,
        User $merchant,
        Channel $channel
    ): array {
        // 檢查金額範圍
        $channelAmounts = ChannelAmount::where("channel_code", $channel->getKey())
            ->orderBy(DB::raw("max_amount - min_amount"))
            ->get();

        $channelAmount = $channelAmounts
            ->filter(function ($channelAmount) use ($context) {
                return ($context->amount >= $channelAmount->min_amount &&
                        $context->amount <= $channelAmount->max_amount) ||
                    ($channelAmount->fixed_amount &&
                        in_array($context->amount, $channelAmount->fixed_amount));
            })
            ->first();

        if (!$channelAmount) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::invalidAmount());
        }

        [$merchantUserChannel, $channelAmount] = $this->findSuitableUserChannel(
            $merchant,
            $channel,
            $context->amount
        );

        if (!$merchantUserChannel || !$channelAmount) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::channelUnavailable());
        }

        // 檢查最小金額
        if (
            !is_null($merchantUserChannel->min_amount) &&
            $this->bcMath->gtZero($merchantUserChannel->min_amount) &&
            $this->bcMath->lt($context->amount, $merchantUserChannel->min_amount)
        ) {
            throw new TransactionValidationException(
                ThirdPartyErrorResponse::invalidMinAmount($merchantUserChannel->min_amount)
            );
        }

        // 檢查最大金額
        if (
            !is_null($merchantUserChannel->max_amount) &&
            $this->bcMath->gtZero($merchantUserChannel->max_amount) &&
            $this->bcMath->gt($context->amount, $merchantUserChannel->max_amount)
        ) {
            throw new TransactionValidationException(
                ThirdPartyErrorResponse::invalidMaxAmount($merchantUserChannel->max_amount)
            );
        }

        // 檢查是否需要真實姓名
        if (
            $merchantUserChannel->real_name_enable &&
            $channel->real_name_enable &&
            !$context->realName
        ) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::realNameRequired());
        }

        return [$merchantUserChannel, $channelAmount];
    }

    // ─── 交易建立方法 ───

    private function findOrCreateTransaction(
        CreateTransactionContext $context,
        User $merchant,
        Channel $channel,
        UserChannel $merchantUserChannel
    ): Transaction {
        $transaction = Transaction::where([
            ["to_id", $merchant->getKey()],
            ["type", Transaction::TYPE_PAUFEN_TRANSACTION],
            ["order_number", $context->orderNumber],
        ])->first();

        if ($transaction) {
            // 四方 API 不允許重複訂單號
            if ($context->isThirdParty) {
                throw new TransactionValidationException(ThirdPartyErrorResponse::duplicateOrderNumber());
            }
            return $transaction;
        }

        // 檢查頻率限制
        $this->checkRateLimit($context, $merchant);

        return $this->createTransaction($context, $merchant, $channel);
    }

    /**
     * @throws TransactionValidationException
     */
    private function checkRateLimit(CreateTransactionContext $context, User $merchant): void
    {
        if (!$this->featureToggleRepository->enabled(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT)) {
            return;
        }

        $transactionCreationRateLimitCount = max(
            $this->featureToggleRepository->valueOf(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT, 5),
            1
        );

        $clientIp = $context->clientIp ?? $this->whitelistedIpManager->extractIpFromRequest(request());

        if (empty($clientIp) || !filter_var($clientIp, FILTER_VALIDATE_IP)) {
            return;
        }

        $count = Transaction::where("client_ipv4", ip2long($clientIp))
            ->whereNotIn("status", [
                Transaction::STATUS_MATCHING,
                Transaction::STATUS_MATCHING_TIMED_OUT,
            ])
            ->where("created_at", ">", now()->subMinutes(10))
            ->count();

        if ($count >= $transactionCreationRateLimitCount) {
            $this->notificationUtil->notifyBusyPayingBlocked(
                $merchant,
                $context->orderNumber,
                $clientIp,
                $context->amount
            );
            throw new TransactionValidationException(ThirdPartyErrorResponse::rateLimitExceeded());
        }
    }

    private function createTransaction(
        CreateTransactionContext $context,
        User $merchant,
        Channel $channel
    ): Transaction {
        $clientIp = $context->clientIp ?? Arr::last(request()->ips());

        $factory = $this->transactionFactory
            ->clientIpv4($clientIp)
            ->amount($context->amount)
            ->orderNumber($context->orderNumber)
            ->notifyUrl($context->notifyUrl)
            ->realName($context->realName);

        if ($channel->code == Channel::CODE_USDT) {
            $usdtUtil = app(UsdtUtil::class);
            $binanceUsdtRate = $usdtUtil->getRate()["rate"];
            $usdtRate = $context->usdtRate ?? $binanceUsdtRate;
            $factory = $factory->usdtRate($usdtRate, $binanceUsdtRate);
        }

        if ($channel->code == Channel::CODE_DC_BANK) {
            $factory = $factory->toData(["bank_name" => $context->bankName]);
        }

        if ($context->returnUrl) {
            $factory = $factory->toData(["return_url" => $context->returnUrl]);
        }

        if ($channel->note_enable) {
            if ($channel->note_type || $channel->code == Channel::CODE_RE_ALIPAY) {
                $factory->note($this->transactionNoteUtil->randomNote($context->amount, $channel));
            }
        }

        if ($channel->floating_enable) {
            $floatingAmount = $this->floatingAmount($context->amount, $channel->floating);

            if (!$this->featureToggleRepository->enabled(FeatureToggle::MAX_AMOUNT_TO_START_FLOATING)) {
                $factory->floatingAmount($floatingAmount);
            } else {
                if ($this->bcMath->lte(
                    $context->amount,
                    $this->featureToggleRepository->valueOf(FeatureToggle::MAX_AMOUNT_TO_START_FLOATING, "2000")
                )) {
                    $factory->floatingAmount($floatingAmount);
                }
            }
        }

        $transaction = $factory->paufenTransactionTo($merchant, $channel);

        if ($channel->order_timeout_enable) {
            MarkPaufenTransactionMatchingTimedOut::dispatch($transaction)
                ->delay(now()->addSeconds($channel->order_timeout));
        }

        return $transaction;
    }

    private function floatingAmount($originalAmount, $maxFloating): string
    {
        if ($maxFloating == 0) {
            return $originalAmount;
        }

        $step = 0.01;
        $absMax = $this->bcMath->abs($maxFloating);

        if ($maxFloating > 0) {
            $availableFloatings = range($step, $absMax, $step);
        } else {
            $availableFloatings = range(-$step, -$absMax, -$step);
        }

        $randomFloat = count($availableFloatings) > 0 ? Arr::random($availableFloatings) : 0;

        return $this->bcMath->add($originalAmount, (string) $randomFloat);
    }

    // ─── 匹配方法 ───

    private function attemptMatching(
        CreateTransactionContext $context,
        Transaction $transaction,
        User $merchant,
        Channel $channel,
        UserChannel $merchantUserChannel,
        ChannelAmount $channelAmount
    ): CreateTransactionResult {
        $isLocalUserChannelAccount = false;

        // 嘗試本地碼商匹配
        if (
            !$merchant->third_channel_enable ||
            ($merchant->third_channel_enable && $merchant->include_self_providers && mt_rand(0, 1))
        ) {
            LocalUserChannelAccount:
            $isLocalUserChannelAccount = true;

            $providerUserChannelAccounts = $this->findSuitableUserChannelAccounts(
                $transaction,
                $channel,
                $merchantUserChannel,
                $channelAmount
            );

            foreach ($providerUserChannelAccounts as $providerUserChannelAccount) {
                if ($providerUserChannelAccount) {
                    try {
                        $result = $this->matchWithLocalProvider(
                            $transaction,
                            $providerUserChannelAccount,
                            $channel,
                            $merchantUserChannel
                        );
                        if ($result) {
                            return $result;
                        }
                    } catch (Exception $e) {
                        Log::error($e, [
                            $transaction->system_order_number,
                            $transaction->order_number,
                        ]);
                    }
                }
            }
        }

        $transaction->refresh();

        // 嘗試四方通道匹配
        if ($merchant->third_channel_enable && $transaction->status == Transaction::STATUS_MATCHING) {
            $result = $this->matchWithThirdChannel(
                $context,
                $transaction,
                $merchant,
                $channel,
                $merchantUserChannel,
                $isLocalUserChannelAccount
            );

            if ($result) {
                return $result;
            }

            // 四方匹配失敗，嘗試本地碼商
            if (!$isLocalUserChannelAccount && $merchant->include_self_providers) {
                goto LocalUserChannelAccount;
            }
        }

        // 匹配失敗
        $transaction->update(["status" => Transaction::STATUS_MATCHING_TIMED_OUT]);
        $this->notifyMatchingTimeout($transaction);

        return CreateTransactionResult::matchingTimedOut($transaction);
    }

    private function matchWithLocalProvider(
        Transaction $transaction,
        UserChannelAccount $providerUserChannelAccount,
        Channel $channel,
        UserChannel $merchantUserChannel
    ): ?CreateTransactionResult {
        DB::transaction(function () use ($transaction, $providerUserChannelAccount, $channel) {
            $this->transactionFactory->paufenTransactionFrom($providerUserChannelAccount, $transaction);

            if (!$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
                $this->walletUtil->withdraw(
                    $transaction->fromWallet,
                    $transaction->floating_amount,
                    $transaction->system_order_number,
                    "transaction"
                );
            }

            if ($channel->transaction_timeout_enable) {
                MarkPaufenTransactionPayingTimedOut::dispatch($transaction->id)
                    ->delay(now()->addSeconds($channel->transaction_timeout));
            }
        });

        $userId = $providerUserChannelAccount->user_id;
        Cache::put("users_{$userId}_new_transaction", true, 60);

        if (!$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            User::where(["id" => $userId])->update(["last_matched_at" => now()]);
        }

        $providerUserChannelAccount->update(["last_matched_at" => now()]);

        return $this->buildResult($transaction->refresh());
    }

    private function matchWithThirdChannel(
        CreateTransactionContext $context,
        Transaction $transaction,
        User $merchant,
        Channel $channel,
        UserChannel $merchantUserChannel,
        bool $isLocalUserChannelAccount
    ): ?CreateTransactionResult {
        $country = $channel->country;

        if (!Redis::set("{$transaction->order_number}:lock", 1, "EX", 300, "NX")) {
            Log::info("{$transaction->order_number} 三方通道已锁");
            return null;
        }

        $channelList = MerchantThirdChannel::with("thirdChannel.channel")
            ->where("owner_id", $merchant->id)
            ->where("deposit_min", "<=", $context->amount)
            ->where("deposit_max", ">=", $context->amount)
            ->whereHas("thirdChannel", function (Builder $query) {
                $query->where("status", ThirdChannel::STATUS_ENABLE)
                    ->where("type", "!=", ThirdChannel::TYPE_WITHDRAW_ONLY);
            })
            ->whereHas("thirdChannel.channel", function (Builder $query) use ($channel) {
                $query->where("status", Channel::STATUS_ENABLE)
                    ->where("channel_code", $channel->getKey());
            })
            ->get()
            ->shuffle();

        $clientIp = $context->clientIp ?? $this->whitelistedIpManager->extractIpFromRequest(request());

        foreach ($channelList as $thirdchannel) {
            $path = "App\ThirdChannel\\" . $thirdchannel->thirdChannel->class;
            $api = new $path($context->channelCode);

            preg_match(
                "/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/",
                $api->depositUrl,
                $url
            );

            $apiUrl = preg_replace(
                "/{$url[1]}/",
                $thirdchannel->thirdChannel->custom_url,
                $api->depositUrl
            );

            $orderNumber = $thirdchannel->thirdChannel->enable_system_order_number
                ? $transaction->system_order_number
                : $context->orderNumber;

            $data = [
                "url" => $apiUrl,
                "callback_url" => config("app.url") . "/api/v1/callback/" . $context->orderNumber,
                "merchant" => $thirdchannel->thirdChannel->merchant_id,
                "key" => $thirdchannel->thirdChannel->key,
                "key2" => $thirdchannel->thirdChannel->key2,
                "key3" => $thirdchannel->thirdChannel->key3,
                "key4" => $thirdchannel->thirdChannel->key4,
                "proxy" => $thirdchannel->thirdChannel->proxy,
                "request" => request(),
                "client_ip" => $clientIp,
                "order_number" => $orderNumber,
                "system_order_number" => $transaction->system_order_number,
            ];

            if (property_exists($api, "rematchUrl")) {
                $data["rematchUrl"] = preg_replace(
                    "/{$url[1]}/",
                    $thirdchannel->thirdChannel->custom_url,
                    $api->rematchUrl
                );
            }

            $return_data = $api->sendDeposit($data);

            if ($return_data["success"]) {
                $transaction->refresh();
                if ($transaction->status == Transaction::STATUS_PAYING) {
                    return $this->buildResult($transaction->refresh());
                }

                $to = $transaction->to_channel_account;
                $to["thirdchannel_cashier_url"] = $return_data["data"]["pay_url"] ?? "";
                $to['receiver_account'] = $return_data["data"]["receiver_account"] ?? "";
                $to['receiver_name'] = $return_data["data"]["receiver_name"] ?? "";
                $to['receiver_bank_name'] = $return_data["data"]["receiver_bank_name"] ?? "";
                $to['receiver_bank_branch'] = $return_data["data"]["receiver_bank_branch"] ?? "";

                $transaction->update([
                    "status" => Transaction::STATUS_THIRD_PAYING,
                    "thirdchannel_id" => $thirdchannel["thirdchannel_id"],
                    "to_channel_account" => $to,
                    "note" => $return_data["data"]["note"] ?? $transaction->note,
                    "matched_at" => now(),
                ]);

                $this->transactionFactory->createPaufenTransactionFees(
                    $transaction->refresh(),
                    $merchantUserChannel->channelGroup
                );

                $cashierUrl = $thirdchannel->thirdChannel->use_third_cashier_url
                    ? $to["thirdchannel_cashier_url"]
                    : urldecode(route("api.v1.cashier", $transaction->system_order_number));

                $matchedInfo = MatchedInfo::fromThirdChannelResponse(
                    $return_data["data"] ?? [],
                    $transaction->note
                );

                return CreateTransactionResult::thirdPaying($transaction->refresh(), $cashierUrl, $matchedInfo);
            } else {
                if (isset($return_data["msg"]) && $return_data["msg"]) {
                    TransactionNote::create([
                        "user_id" => 0,
                        "transaction_id" => $transaction->id,
                        "note" => $thirdchannel->thirdChannel->name . ": " . $return_data['msg']
                    ]);
                }
            }
        }

        return null;
    }

    private function markAsMatchingTimedOut(Transaction $transaction): void
    {
        $updatedRow = Transaction::where([
            ["id", $transaction->getKey()],
            ["type", Transaction::TYPE_PAUFEN_TRANSACTION],
            ["status", Transaction::STATUS_MATCHING],
        ])->update(["status" => Transaction::STATUS_MATCHING_TIMED_OUT]);

        throw_if($updatedRow > 1, new \RuntimeException("Unexpected row being updated"));
    }

    private function notifyMatchingTimeout(Transaction $transaction): void
    {
        if (Redis::set("notify:matched:timeout", 1, "EX", 5, "NX")) {
            Notification::route(
                "telegram",
                config("services.telegram-bot-api.system-admin-group-id")
            )->notify(
                new MatchedTimeout(
                    $transaction->order_number,
                    $transaction->to->name,
                    $transaction->channel->name,
                    $transaction->amount
                )
            );
        }
    }

    // ─── 結果建立方法 ───

    private function buildResult(Transaction $transaction): CreateTransactionResult
    {
        $channel = $transaction->channel;
        $thirdchannelUrl = $transaction->to_channel_account["thirdchannel_cashier_url"] ?? "";

        if ($transaction->thirdchannel_id && $thirdchannelUrl) {
            $matchedInfo = MatchedInfo::fromThirdChannelResponse(
                $transaction->to_channel_account,
                $transaction->note
            );
            return CreateTransactionResult::thirdPaying($transaction, $thirdchannelUrl, $matchedInfo);
        }

        if ($transaction->paying()) {
            $qrCodePath = $this->qrCodeS3Path($transaction);
            $matchedInfo = MatchedInfo::fromUserChannelAccount($transaction->from_channel_account ?? []);

            return CreateTransactionResult::matched($transaction, $qrCodePath, $matchedInfo);
        }

        if ($transaction->matching()) {
            return CreateTransactionResult::matching($transaction);
        }

        if ($transaction->matchingTimedOut()) {
            return CreateTransactionResult::matchingTimedOut($transaction);
        }

        if ($transaction->payingTimedOut()) {
            return CreateTransactionResult::payingTimedOut($transaction);
        }

        if ($transaction->success()) {
            return CreateTransactionResult::success($transaction);
        }

        return CreateTransactionResult::matching($transaction);
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

    // ─── 通道匹配方法（從 Trait 遷移）───

    private function findSuitableUserChannel(User $merchant, Channel $channel, string $amount): array
    {
        $userChannels = UserChannel::with('channelGroup.channelAmount')
            ->where([
                ['user_id', $merchant->getKey()],
                ['status', UserChannel::STATUS_ENABLED]
            ])
            ->whereHas('channelGroup.channelAmounts', function (Builder $channelGroups) use ($channel) {
                $channelGroups->where('channel_code', $channel->getKey());
            })
            ->get();

        $userChannels = $userChannels->filter(function ($userChannel) use ($amount) {
            $channelAmount = $userChannel->channelGroup->channelAmount;
            $minAmount = $userChannel->min_amount ?? $channelAmount->min_amount;
            $maxAmount = $userChannel->max_amount ?? $channelAmount->max_amount;

            if ($minAmount && $maxAmount) {
                return $amount >= $minAmount && $amount <= $maxAmount;
            }

            if ($channelAmount->fixed_amount) {
                return in_array($amount, $channelAmount->fixed_amount);
            }

            return false;
        });

        if (!$userChannels) {
            return [null, null];
        }

        $userChannel = $userChannels->filter(function ($channel) use ($amount) {
            if ($channel->min_amount && $channel->min_amount > $amount) {
                return false;
            }
            if ($channel->max_amount && $channel->max_amount < $amount) {
                return false;
            }
            return true;
        })->first();

        if (!$userChannel) {
            return [null, null];
        }

        $channelAmounts = ChannelAmount::where([
            ['channel_group_id', $userChannel->channel_group_id],
        ])->get();

        $channelAmount = $channelAmounts->filter(function ($channelAmount) use ($amount) {
            return ($amount >= $channelAmount->min_amount && $amount <= $channelAmount->max_amount) ||
                   ($channelAmount->fixed_amount && in_array($amount, $channelAmount->fixed_amount));
        })->first();

        if (!$channelAmount) {
            return [$userChannel, null];
        }

        return [$userChannel, $channelAmount];
    }

    private function findSuitableUserChannelAccounts(
        Transaction $transaction,
        Channel $channel,
        UserChannel $merchantUserChannel,
        ChannelAmount $channelAmount
    ): Collection {
        DB::enableQueryLog();

        $query = $this->initializeAccountQuery();

        $this->applyProviderConcurrentLimit($query);
        $this->applyBalanceLimits($query, $transaction);
        $this->applySingleTransactionLimits($query, $transaction);
        $this->applyFloatingAmountRestriction($query, $channel, $transaction);
        $this->applyPayingTransactionsRestriction($query, $transaction, $channel);
        $this->applyUserAndAccountStatus($query);
        $this->applyAccountType($query);
        $this->applyChannelAmountAndFee($query, $channelAmount, $merchantUserChannel);
        $this->applyReadyForMatching($query);
        $this->applyTimeLimit($query);
        $this->applyWalletBalanceConditions($query, $transaction);
        $this->applyBankRestrictions($query, $channel);
        $this->applyTransactionGroupConditions($query, $transaction);
        $this->applyGeolocationMatching($query, $channel);
        $this->applyMatchingOrder($query);

        $providerUserChannelAccounts = $query->get(['user_channel_accounts.*']);

        if ($providerUserChannelAccounts->isEmpty()) {
            return collect();
        }

        $filteredAccounts = $this->filterAccountsByAmountRestrictions($providerUserChannelAccounts, $transaction);
        $matchedAccounts = $this->matchLastAccountIfRequested($filteredAccounts, $channel);

        return $this->replaceBankNames($matchedAccounts);
    }

    private function initializeAccountQuery()
    {
        return UserChannelAccount::query()
            ->with('bank', 'channelAmount')
            ->join('users', 'users.id', '=', 'user_channel_accounts.user_id');
    }

    private function applyProviderConcurrentLimit($query): void
    {
        if ($this->featureToggleRepository->enabled(FeatureToggle::PROVIDER_CONCURRENT_PAYING_TRANSACTION_LIMIT)) {
            $limitCount = $this->featureToggleRepository->valueOf(FeatureToggle::PROVIDER_CONCURRENT_PAYING_TRANSACTION_LIMIT);

            $query->leftJoinSub(
                $this->getPayingTransactionsSubquery(),
                'paying_transactions',
                'paying_transactions.from_id',
                '=',
                'user_channel_accounts.user_id'
            )->where(DB::raw('IFNULL(paying_transactions.total_count, 0)'), '<', $limitCount);
        }
    }

    private function getPayingTransactionsSubquery()
    {
        return Transaction::select(['from_id', DB::raw('COUNT(transactions.id) AS total_count')])
            ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->where('status', Transaction::STATUS_PAYING)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->groupBy('from_id');
    }

    private function applyBalanceLimits($query, Transaction $transaction): void
    {
        $query->where(function ($q) use ($transaction) {
            $q->where('user_channel_accounts.balance_limit', '>=', DB::raw("user_channel_accounts.balance + {$transaction->floating_amount}"))
                ->orWhere('user_channel_accounts.balance_limit', '0')
                ->orWhereNull('user_channel_accounts.balance_limit');
        });

        if ($this->featureToggleRepository->enabled(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT)) {
            $dailyLimit = $this->featureToggleRepository->valueOf(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT);
            $query->where(function ($q) use ($dailyLimit, $transaction) {
                $q->orWhere('daily_status', '0')
                    ->orWhere(DB::raw("IFNULL(daily_limit, {$dailyLimit})"), '>=', DB::raw("daily_total + {$transaction->floating_amount}"));
            });
        }

        if ($this->featureToggleRepository->enabled(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT)) {
            $monthlyLimit = $this->featureToggleRepository->valueOf(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT);
            $query->where(function ($q) use ($monthlyLimit, $transaction) {
                $q->orWhere('monthly_status', '0')
                    ->orWhere(DB::raw("IFNULL(monthly_limit, {$monthlyLimit})"), '>=', DB::raw("monthly_total + {$transaction->floating_amount}"));
            });
        }
    }

    private function applySingleTransactionLimits($query, Transaction $transaction): void
    {
        $query->where(function ($q) use ($transaction) {
            $q->where('user_channel_accounts.single_min_limit', '<=', $transaction->floating_amount)
                ->orWhereNull('user_channel_accounts.single_min_limit');
        })->where(function ($q) use ($transaction) {
            $q->where('user_channel_accounts.single_max_limit', '>=', $transaction->floating_amount)
                ->orWhere('user_channel_accounts.single_max_limit', '0')
                ->orWhereNull('user_channel_accounts.single_max_limit');
        });
    }

    private function applyFloatingAmountRestriction($query, Channel $channel, Transaction $transaction): void
    {
        if ($channel->floating_enable) {
            $subquery = Transaction::select(['from_id', DB::raw('COUNT(transactions.id) AS total_count')])
                ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
                ->where('channel_code', $channel->code)
                ->where('status', Transaction::STATUS_PAYING)
                ->where('floating_amount', '=', $transaction->floating_amount)
                ->where('created_at', '>=', now()->subMinutes(30))
                ->groupBy('from_id');

            $query->leftJoinSub($subquery, 'paying_transactions', 'paying_transactions.from_id', '=', 'user_channel_accounts.user_id')
                ->where(DB::raw('IFNULL(paying_transactions.total_count, 0)'), '=', 0);
        }
    }

    private function applyPayingTransactionsRestriction($query, Transaction $transaction, Channel $channel): void
    {
        if (!request()->input('match_last_account') && !$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $isAlipay = in_array($transaction->channel_code, [
                Channel::CODE_QR_ALIPAY,
                Channel::CODE_ALIPAY_SAC,
                Channel::CODE_ALIPAY_BAC,
                Channel::CODE_ALIPAY_GC
            ]);

            $featureToggle = $isAlipay
                ? FeatureToggle::ALLOW_QR_ALIPAY_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT
                : FeatureToggle::ALLOW_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT;

            $query->whereDoesntHave('devicePayingTransactions.transaction', function ($q) use ($transaction, $channel, $featureToggle) {
                $q->where('channel_code', $transaction->channel_code);

                if (!$channel->max_one_ignore_amount) {
                    $q->where('amount', $transaction->floating_amount);
                }

                if ($this->featureToggleRepository->enabled($featureToggle)) {
                    $q->where('amount', $transaction->floating_amount)
                        ->whereRaw('JSON_CONTAINS(to_channel_account, ?)', json_encode(['real_name' => $transaction->to_channel_account['real_name'] ?? '']));
                }
            });
        }
    }

    private function applyUserAndAccountStatus($query): void
    {
        $query->where([
            ['users.transaction_enable', User::STATUS_ENABLE],
            ['users.status', User::STATUS_ENABLE],
            ['user_channel_accounts.status', UserChannelAccount::STATUS_ONLINE],
        ]);
    }

    private function applyAccountType($query): void
    {
        $query->where('user_channel_accounts.type', '!=', UserChannelAccount::TYPE_WITHDRAW);
    }

    private function applyChannelAmountAndFee($query, ChannelAmount $channelAmount, UserChannel $merchantUserChannel): void
    {
        $query->where([
            ['channel_amount_id', $channelAmount->getKey()],
            ['fee_percent', '<=', $merchantUserChannel->fee_percent],
        ]);
    }

    private function applyReadyForMatching($query): void
    {
        if (!$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $query->where('users.ready_for_matching', true);
        }
    }

    private function applyTimeLimit($query): void
    {
        if ($this->featureToggleRepository->enabled(FeatureToggle::LATE_NIGHT_BANK_LIMIT)) {
            $query->where('time_limit_disabled', false);
        }
    }

    private function applyWalletBalanceConditions($query, Transaction $transaction): void
    {
        if (!$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $query->whereHas('wallet', function (Builder $walletBuilder) use ($transaction) {
                $minimumRequiredBalance = $transaction->floating_amount;

                if ($this->featureToggleRepository->enabled(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE)) {
                    $minimumRequiredBalance = $this->bcMath->max(
                        $minimumRequiredBalance,
                        $this->featureToggleRepository->valueOf(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE, 0)
                    );
                }

                $walletBuilder->where(DB::raw('balance - frozen_balance'), '>=', $minimumRequiredBalance);

                if ($this->featureToggleRepository->enabled(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE_IN_PERCENT)) {
                    $value = $this->featureToggleRepository->valueOf(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE_IN_PERCENT, 0);
                    if ($value > 0) {
                        $percent = $value / 100;
                        $walletBuilder->where(DB::raw("(balance - frozen_balance) * $percent"), '>=', $transaction->floating_amount);
                    }
                }
            });
        }
    }

    private function applyBankRestrictions($query, Channel $channel): void
    {
        if (request()->filled('bank_name') && $channel->code != Channel::CODE_DC_BANK) {
            $query->whereHas('bank', function (Builder $channelBanks) {
                $channelBanks->where('name', request()->input('bank_name'));
            });
        }
    }

    private function applyTransactionGroupConditions($query, Transaction $transaction): void
    {
        $currentMerchantInTransactionGroup = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->where('owner_id', $transaction->to_id)
            ->exists();

        $query->when($currentMerchantInTransactionGroup, function (Builder $userChannelAccounts) use ($transaction) {
            $userChannelAccounts->whereHas('transactionGroups', function (Builder $transactionGroups) use ($transaction) {
                $transactionGroups->where('owner_id', $transaction->to_id)
                    ->where('transaction_type', Transaction::TYPE_PAUFEN_TRANSACTION);
            });
        })->when(!$currentMerchantInTransactionGroup, function (Builder $userChannelAccounts) {
            $userChannelAccounts->whereDoesntHave('transactionGroups');
        });
    }

    private function applyGeolocationMatching($query, Channel $channel): void
    {
        if ($channel->geolocation_match) {
            $ip = request()->input('client_ip', $this->whitelistedIpManager->extractIpFromRequest(request()));
            $city = optional(Location::get($ip))->cityName;
            $city = str_replace('\'', ' ', $city);
            $query->orderByRaw("users.last_login_city='{$city}' DESC");
        }
    }

    private function applyMatchingOrder($query): void
    {
        if (!$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $query->orderBy('users.last_matched_at');
        }

        $matchType = $this->featureToggleRepository->valueOf(FeatureToggle::TRANSACTION_MATCH_TYPE);
        switch ($matchType) {
            case 0: // 輪詢匹配
                $query->orderBy('user_channel_accounts.last_matched_at');
                break;
            case 1: // 順序匹配
                break;
            case 2: // 隨機匹配
                $query->orderByRaw('RAND()');
                break;
        }
    }

    private function filterAccountsByAmountRestrictions(Collection $providerUserChannelAccounts, Transaction $transaction): Collection
    {
        return $providerUserChannelAccounts->filter(function ($userChannelAccount) use ($transaction) {
            $channelAmount = $userChannelAccount->channelAmount;
            $minAmount = $userChannelAccount->min_amount ?? $channelAmount->min_amount;
            $maxAmount = $userChannelAccount->max_amount ?? $channelAmount->max_amount;

            if ($minAmount && $maxAmount) {
                return $transaction->amount >= $minAmount && $transaction->amount <= $maxAmount;
            }

            if ($channelAmount->fixed_amount) {
                return in_array($transaction->amount, $channelAmount->fixed_amount);
            }

            return false;
        });
    }

    private function matchLastAccountIfRequested(Collection $filteredAccounts, Channel $channel): Collection
    {
        if (request()->input('match_last_account') && request()->has('real_name')) {
            $lastMatch = Transaction::where('channel_code', $channel->code)
                ->whereNotNull('from_channel_account_id')
                ->where('to_channel_account->real_name', request()->input('real_name'))
                ->orderByDesc('id')
                ->first();

            if ($lastMatch && $filteredAccounts->contains('id', $lastMatch->from_channel_account_id)) {
                return collect([$lastMatch->fromChannelAccount]);
            }
        }
        return $filteredAccounts;
    }

    private function replaceBankNames(Collection $matchedAccounts): Collection
    {
        return $matchedAccounts->map(function ($account) {
            $detail = $account->detail;
            $bankData = $account->bank;

            if (!empty($bankData) && isset($bankData->name) && data_get($detail, 'bank_name')) {
                data_set($detail, 'bank_name', $bankData->name);
                $account->detail = $detail;
            }

            return $account;
        });
    }

    // ─── 輔助方法 ───

    private function withSign(Collection $postData, User $merchant): Collection
    {
        $postData = $postData->sortKeys();

        return $postData->merge([
            'sign' => strtolower(md5(urldecode(http_build_query(array_filter($postData->toArray())) . '&secret_key=' . $merchant->secret_key)))
        ]);
    }
}

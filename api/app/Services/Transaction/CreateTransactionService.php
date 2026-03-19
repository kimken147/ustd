<?php

namespace App\Services\Transaction;

use App\Jobs\MarkPaufenTransactionMatchingTimedOut;
use App\Jobs\MarkPaufenTransactionPayingTimedOut;
use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\FeatureToggle;
use App\Models\MerchantThirdChannel;
use App\Models\ThirdChannel;
use App\Models\Transaction;
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
use App\DTOs\TransactionParams;
use App\Utils\TransactionFactory;
use App\Utils\TransactionMutator;
use App\Utils\TransactionNoteUtil;
use App\Models\UsdtDepositMonitor;
use App\Services\UserChannelAccount\UserChannelAccountService;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
class CreateTransactionService
{
    public function __construct(
        private BCMathUtil $bcMath,
        private FeatureToggleRepository $featureToggleRepository,
        private TransactionNoteUtil $transactionNoteUtil,
        private TransactionFactory $transactionFactory,
        private TransactionMutator $transactionMutator,
        private TransactionFeeService $transactionFeeService,
        private WalletUtil $walletUtil,
        private WhitelistedIpManager $whitelistedIpManager,
        private AccountMatchingQueryBuilder $accountMatchingQueryBuilder,
        private TransactionValidationService $validationService,
        private TransactionStatusService $statusService,
    ) {}

    /**
     * 建立交易（主入口）
     *
     * @throws TransactionValidationException
     */
    public function create(CreateTransactionContext $context): CreateTransactionResult
    {
        $channel = $this->validationService->validateAndGetChannel($context);
        $merchant = $this->validationService->validateAndGetMerchant($context, $channel);
        [$merchantUserChannel, $channelAmount] = $this->validationService->validateAndGetUserChannel($context, $merchant, $channel);

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
                $this->statusService->markAsSuccess($transaction, null, true, false, false);
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
                $this->statusService->markAsFailed($transaction, null, $returnCallback["fail"], false);

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
        $query = User::where('username', $context->username)
            ->where('role', User::ROLE_MERCHANT);

        if ($context->secretKey) {
            $query->where('secret_key', $context->secretKey);
        }

        $merchant = $query->first();

        if (!$merchant) {
            throw new TransactionValidationException(
                ThirdPartyErrorResponse::badRequest(400, __('common.Merchant number error'))
            );
        }

        $channel = Channel::where('code', $context->channelCode)->first();
        if (!$channel) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::channelNotFound());
        }

        [$userChannel, $channelAmount] = $this->validationService->findSuitableUserChannel($merchant, $channel, $context->amount);

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

        $url = route('api.v1.create-transactions', $this->validationService->withSign($postData, $merchant)->toArray());

        return new DemoResult($url);
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
        $this->validationService->checkRateLimit($context, $merchant);

        return $this->createTransaction($context, $merchant, $channel);
    }

    private function createTransaction(
        CreateTransactionContext $context,
        User $merchant,
        Channel $channel
    ): Transaction {
        $clientIp = $context->clientIp ?? Arr::last(request()->ips());

        $toData = [];
        if ($context->returnUrl) {
            $toData["return_url"] = $context->returnUrl;
        }

        $note = null;
        if ($channel->note_enable) {
            if ($channel->note_type) {
                $note = $this->transactionNoteUtil->randomNote($context->amount, $channel);
            }
        }

        $floatingAmount = null;
        if ($channel->floating_enable) {
            $computed = $this->floatingAmount($context->amount, $channel->floating);

            if (!$this->featureToggleRepository->enabled(FeatureToggle::MAX_AMOUNT_TO_START_FLOATING)) {
                $floatingAmount = $computed;
            } else {
                if ($this->bcMath->lte(
                    $context->amount,
                    $this->featureToggleRepository->valueOf(FeatureToggle::MAX_AMOUNT_TO_START_FLOATING, "2000")
                )) {
                    $floatingAmount = $computed;
                }
            }
        }

        $params = new TransactionParams(
            amount: $context->amount,
            clientIpv4: $clientIp,
            floatingAmount: $floatingAmount,
            note: $note,
            notifyUrl: $context->notifyUrl,
            orderNumber: $context->orderNumber,
            realName: $context->realName,
            usdtRate: null,
            binanceUsdtRate: null,
            toData: array_filter($toData),
        );

        $transaction = $this->transactionFactory->paufenTransactionTo($params, $merchant, $channel);

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

            $providerUserChannelAccounts = $this->accountMatchingQueryBuilder->findSuitableUserChannelAccounts(
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
        // USDT 母地址匹配時，自動衍生一次性子地址作為實際收款地址
        $depositAccount = $providerUserChannelAccount;
        if (
            $providerUserChannelAccount->channel_code === Channel::CODE_USDT
            && $providerUserChannelAccount->address_type === UserChannelAccount::ADDRESS_TYPE_MASTER
        ) {
            try {
                $childAccount = app(UserChannelAccountService::class)->createChildAccount(
                    $providerUserChannelAccount,
                    [
                        'is_one_time' => true,
                        'receive_status' => UserChannelAccount::RECEIVE_STATUS_UNUSED,
                        'linked_transaction_id' => $transaction->id,
                        'status' => UserChannelAccount::STATUS_ONLINE,
                    ]
                );
                $depositAccount = $childAccount;
            } catch (\Exception $e) {
                // 衍生失敗時 fallback 到母地址
                Log::warning('Failed to derive child address, falling back to master', [
                    'master_account_id' => $providerUserChannelAccount->id,
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        DB::transaction(function () use ($transaction, $depositAccount, $providerUserChannelAccount, $channel) {
            $this->transactionMutator->paufenTransactionFrom($depositAccount, $transaction);

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

            // 如果是 USDT 通道，建立鏈上監控記錄
            $transaction->refresh();
            if ($transaction->channel_code === Channel::CODE_USDT && $transaction->from_channel_account_id) {
                $account = UserChannelAccount::find($transaction->from_channel_account_id);
                if ($account) {
                    UsdtDepositMonitor::create([
                        'transaction_id' => $transaction->id,
                        'user_channel_account_id' => $account->id,
                        'address' => $account->account ?? '',
                        'chain_network' => data_get($account->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20'),
                        'expected_amount' => $transaction->floating_amount,
                        'status' => UsdtDepositMonitor::STATUS_PENDING,
                        'expires_at' => $channel->transaction_timeout_enable
                            ? now()->addSeconds($channel->transaction_timeout)
                            : null,
                    ]);
                }
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

                $this->transactionFeeService->createPaufenTransactionFees(
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

}

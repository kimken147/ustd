<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Controllers\Controller;
use App\Jobs\MarkPaufenTransactionMatchingTimedOut;
use App\Jobs\MarkPaufenTransactionPayingTimedOut;
use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Models\TransactionNote;
use App\Models\BannedIp;
use App\Models\BannedRealname;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\ThirdPartyResponseUtil;
use App\Utils\TransactionFactory;
use App\Utils\TransactionNoteUtil;
use App\Utils\TransactionUtil;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use App\Utils\UserChannelAccountUtil;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Notification;
use App\Notifications\MatchedTimeout;

//四方
use App\Models\ThirdChannel;
use App\Models\MerchantThirdChannel;
use App\Utils\UsdtUtil;

class CreateTransactionController extends Controller
{
    use UserChannelAccountMatching, UserChannelMatching, MatchedJsonResponse;

    /**
     * @var BCMathUtil
     */
    private $bcMath;

    /**
     * @var FeatureToggleRepository
     */
    private $featureToggleRepository;

    /**
     * @var NotificationUtil
     */
    private $notificationUtil;
    /**
     * @var TransactionNoteUtil
     */
    private $transactionNoteUtil;

    public function __construct(
        NotificationUtil $notificationUtil,
        BCMathUtil $bcMath,
        FeatureToggleRepository $featureToggleRepository,
        TransactionNoteUtil $transactionNoteUtil
    ) {
        $this->notificationUtil = $notificationUtil;
        $this->bcMath = $bcMath;
        $this->featureToggleRepository = $featureToggleRepository;
        $this->transactionNoteUtil = $transactionNoteUtil;

        $this->middleware('parse.textplain.json')->only(['callback']);
    }

    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @param  TransactionFactory  $transactionFactory
     * @param  BCMathUtil  $bcMath
     * @param  WalletUtil  $wallet
     * @param  FeatureToggleRepository  $featureToggleRepository
     * @param  WhitelistedIpManager  $whitelistedIpManager
     * @return JsonResponse
     * @throws ValidationException
     */
    public function __invoke(
        Request $request,
        TransactionFactory $transactionFactory,
        BCMathUtil $bcMath,
        WalletUtil $wallet,
        FeatureToggleRepository $featureToggleRepository,
        WhitelistedIpManager $whitelistedIpManager,
        UserChannelAccountUtil $userChannelAccountUtil
    ) {
        foreach (
            [
                "channel_code",
                "username",
                "amount",
                "notify_url",
                "client_ip",
                "sign",
            ]
            as $requiredAttribute
        ) {
            if (!$request->filled($requiredAttribute)) {
                return response()->json([
                    "http_status_code" => Response::HTTP_BAD_REQUEST,
                    "error_code" =>
                    ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                    "message" => __('common.Missing parameter: :attribute', ['attribute' => $requiredAttribute]),
                ]);
            }
        }

        $channel = Channel::where("code", $request->channel_code)->first();
        if (!$channel) {
            return response()->json([
                "http_status_code" => Response::HTTP_NOT_FOUND,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                "message" => __('common.Channel not found'),
            ]);
        }

        $country = $channel->country;
        $clientIp = $request->input(
            "client_ip",
            $whitelistedIpManager->extractIpFromRequest($request)
        );

        if ($channel->code == Channel::CODE_DC_BANK) {
            if (!$request->has("bank_name")) {
                return response()->json([
                    "http_status_code" => Response::HTTP_BAD_REQUEST,
                    "error_code" =>
                    ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                    "message" => __('common.Missing parameter: :attribute', ['attribute' => 'bank_name']),
                ]);
            }

            $dcCode = "dc_" . strtolower($request->bank_name);
            if (
                !view()->exists("v1.transactions.{$country}.{$dcCode}.matched")
            ) {
                return response()->json([
                    "http_status_code" => Response::HTTP_NOT_FOUND,
                    "error_code" =>
                    ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                    "message" => __('common.Channel not found'),
                ]);
            }
        }

        if (
            BannedIp::where([
                "ipv4" => ip2long($clientIp),
                "type" => BannedIp::TYPE_TRANSACTION,
            ])->exists()
        ) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                "message" => __('common.IP access forbidden'),
            ]);
        }

        if (
            BannedRealname::where([
                "realname" => $request->real_name,
                "type" => BannedRealname::TYPE_TRANSACTION,
            ])->exists()
        ) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                "message" => __('common.Real name access forbidden'),
            ]);
        }

        if (!$channel) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_INVALID_CHANNEL_CODE,
                "message" => __('common.No matching channel'),
            ]);
        }

        if ($channel->status !== Channel::STATUS_ENABLE) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_CHANNEL_TEMPORARY_UNAVAILABLE,
                "message" => __('common.Channel under maintenance'),
            ]);
        }

        /** @var User|null $merchant */
        $merchant = User::where([
            ["username", $request->username],
            ["role", User::ROLE_MERCHANT],
        ])->first();

        if (!$merchant) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_USER_NOT_FOUND,
                "message" => __('common.User not found'),
            ]);
        }

        if ($merchant->disabled()) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_USER_NOT_FOUND,
                "message" => __('common.Account deactivated'),
            ]);
        }

        $parameters = $request->except("sign");

        ksort($parameters);

        $sign = md5(
            urldecode(
                http_build_query($parameters) .
                    "&secret_key=" .
                    $merchant->secret_key
            )
        );

        if (strcasecmp($sign, $request->input("sign"))) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" => ThirdPartyResponseUtil::ERROR_CODE_INVALID_SIGN,
                "message" => __('common.Signature error'),
            ]);
        }

        if (
            $whitelistedIpManager->isNotAllowedToUseThirdPartyApi(
                $merchant,
                $request
            )
        ) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" => ThirdPartyResponseUtil::ERROR_CODE_INVALID_IP,
                "message" => __('common.Please contact admin to add IP to whitelist'),
            ]);
        }

        if (!$merchant->transaction_enable) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_TRANSACTION_DISABLED,
                "message" => __("user.Transaction disabled"),
            ]);
        }

        $totalCost = $bcMath->sum([
            $request->input("amount"),
            $merchant->wallet->available_balance,
        ]);

        if (
            $merchant->balance_limit >= 1 &&
            $bcMath->lt($merchant->balance_limit, $totalCost)
        ) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_INSUFFICIENT_BALANCE,
                "message" => __('common.Balance exceeds limit, please withdraw first'),
            ]);
        }

        $channelAmounts = ChannelAmount::where(
            "channel_code",
            $channel->getKey()
        )
            ->orderBy(DB::raw("max_amount - min_amount"))
            ->get();

        $channelAmount = $channelAmounts
            ->filter(function ($channelAmount) use ($request) {
                return ($request->amount >= $channelAmount->min_amount &&
                    $request->amount <= $channelAmount->max_amount) ||
                    ($channelAmount->fixed_amount &&
                        in_array(
                            $request->amount,
                            $channelAmount->fixed_amount
                        ));
            })
            ->first();

        if (!$channelAmount) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_INVALID_AMOUNT,
                "message" => __('common.Wrong amount, please change and retry'),
            ]);
        }

        /** @var UserChannel $merchantUserChannel */
        [$merchantUserChannel, $channelAmount] = $this->findSuitableUserChannel(
            $merchant,
            $channel,
            $request->amount
        );

        if (!$merchantUserChannel || !$channelAmount) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_CHANNEL_TEMPORARY_UNAVAILABLE,
                "message" => __('common.Channel not found'),
            ]);
        }

        if (
            !is_null($merchantUserChannel->min_amount) &&
            $bcMath->gtZero($merchantUserChannel->min_amount) &&
            $bcMath->lt($request->amount, $merchantUserChannel->min_amount)
        ) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT,
                "message" => __('transaction.Amount greater', ['amount' => $merchantUserChannel->min_amount]),
            ]);
        }

        if (
            !is_null($merchantUserChannel->max_amount) &&
            $bcMath->gtZero($merchantUserChannel->max_amount) &&
            $bcMath->gt($request->amount, $merchantUserChannel->max_amount)
        ) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_INVALID_MAX_AMOUNT,
                "message" => __('transaction.Amount less', ['amount' => $merchantUserChannel->max_amount]),
            ]);
        }

        if (
            $merchantUserChannel->real_name_enable &&
            $channel->real_name_enable &&
            !$request->filled("real_name")
        ) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                "message" => __('common.Missing parameter: :attribute', ['attribute' => 'real_name']),
            ]);
        }

        $transaction = Transaction::where([
            ["to_id", $merchant->getKey()],
            ["type", Transaction::TYPE_PAUFEN_TRANSACTION],
            ["order_number", $request->order_number],
        ])->first();

        if ($transaction) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_DUPLICATE_ORDER_NUMBER,
                "message" => __('common.Duplicate number'),
            ]);
        }

        if (
            $blockedResponse = $this->blockBusyPaying(
                $request,
                $featureToggleRepository
            )
        ) {
            $this->notificationUtil->notifyBusyPayingBlocked(
                $merchant,
                $request->order_number,
                $request->input("client_ip"),
                $request->amount
            );

            return $blockedResponse;
        }

        $transaction = $this->createTransaction(
            $merchant,
            $request,
            $channel,
            $transactionFactory
        );

        $isLocalUserChannelAccount = false; # 是否已嘗試匹配本地碼商

        // 嘗試自已的跑分匹配
        $providerUserChannelAccount = null;
        if (
            !$merchant->third_channel_enable ||
            ($merchant->third_channel_enable &&
                $merchant->include_self_providers &&
                mt_rand(0, 1))
        ) {
            LocalUserChannelAccount: # 三方匹配失敗，嘗試本地碼商
            $isLocalUserChannelAccount = true;

            // 沒啟用三方 或者 啟用三方且共用本站碼商且50%
            for (
                $retryCount = 0;
                $retryCount < 1 && !$providerUserChannelAccount;
                $retryCount++
            ) {
                if ($retryCount > 0) {
                    usleep(400 * 1000);
                }

                if (
                    !$merchant->third_channel_enable ||
                    ($merchant->third_channel_enable &&
                        $merchant->include_self_providers)
                ) {
                    // 沒啟用三方 或者 啟用三方且共用本站碼商
                    $providerUserChannelAccounts = $this->findSuitableUserChannelAccounts(
                        $transaction,
                        $channel,
                        $merchantUserChannel,
                        $channelAmount,
                        $featureToggleRepository,
                        $bcMath
                    );

                    foreach (
                        $providerUserChannelAccounts
                        as $providerUserChannelAccount
                    ) {
                        try {
                            DB::transaction(function () use (
                                $transaction,
                                $transactionFactory,
                                $providerUserChannelAccount,
                                $wallet,
                                $channel,
                                $featureToggleRepository
                            ) {
                                $transaction = $transactionFactory->paufenTransactionFrom(
                                    $providerUserChannelAccount,
                                    $transaction
                                );

                                // 非免簽模式，才需要扣碼商錢包餘額
                                if (
                                    !$featureToggleRepository->enabled(
                                        FeatureToggle::CANCEL_PAUFEN_MECHANISM
                                    )
                                ) {
                                    $wallet->withdraw(
                                        $transaction->fromWallet,
                                        $transaction->floating_amount,
                                        $transaction->system_order_number,
                                        $transactionType = "transaction"
                                    );
                                }

                                if ($channel->transaction_timeout_enable) {
                                    MarkPaufenTransactionPayingTimedOut::dispatch(
                                        $transaction->id
                                    )->delay(
                                        now()->addSeconds(
                                            $channel->transaction_timeout
                                        )
                                    );
                                }
                            });

                            $userId = $providerUserChannelAccount->user_id;

                            Cache::put(
                                "users_{$userId}_new_transaction",
                                true,
                                60
                            );

                            // 非免簽模式，才需要連流匹配碼商
                            if (
                                !$featureToggleRepository->enabled(
                                    FeatureToggle::CANCEL_PAUFEN_MECHANISM
                                )
                            ) {
                                User::where(["id" => $userId])->update([
                                    "last_matched_at" => now(),
                                ]);
                            }

                            $providerUserChannelAccount->update([
                                "last_matched_at" => now(),
                            ]);

                            return $this->responseOf(
                                $transaction->refresh(),
                                $featureToggleRepository
                            );
                        } catch (Exception $e) {
                            // 假設匹配到但因為 Race condition 或其他原因導致寫單失敗，讓使用者繼續重新匹配
                            $providerUserChannelAccount = null;
                        }
                    }
                }
            }
        }
        //檢查是否為四方
        if ($merchant->third_channel_enable && $transaction->status == Transaction::STATUS_MATCHING) {
            if (
                !Redis::set(
                    "{$transaction->order_number}:lock",
                    1,
                    "EX",
                    120,
                    "NX"
                )
            ) {
                Log::info("{$transaction->order_number} 三方通道已锁");
                return view(
                    "v1.transactions.{$country}.please-try-later",
                    compact("transaction")
                );
            }

            $channelList = MerchantThirdChannel::with("thirdChannel.channel")
                ->where("owner_id", $merchant->id)
                ->where("deposit_min", "<=", $request->amount)
                ->where("deposit_max", ">=", $request->amount)
                ->whereHas("thirdChannel", function (Builder $query) {
                    $query
                        ->where("status", ThirdChannel::STATUS_ENABLE)
                        ->where("type", "!=", ThirdChannel::TYPE_WITHDRAW_ONLY);
                })
                ->whereHas("thirdChannel.channel", function (
                    Builder $query
                ) use ($channel) {
                    $query
                        ->where("status", Channel::STATUS_ENABLE)
                        ->where("channel_code", $channel->getKey());
                })
                ->get();

            $channelList = $channelList->shuffle(); //打亂排序

            foreach ($channelList as $thirdchannel) {
                $path =
                    "App\ThirdChannel\\" . $thirdchannel->thirdChannel->class;
                $api = new $path($request->channel_code);

                preg_match(
                    "/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/",
                    $api->depositUrl,
                    $url
                );

                $orderNumber = $thirdchannel->thirdChannel->enable_system_order_number ? $transaction->system_order_number : $request->order_number;

                $data = [
                    "url" => preg_replace(
                        "/{$url[1]}/",
                        $thirdchannel->thirdChannel->custom_url,
                        $api->depositUrl
                    ),
                    "callback_url" =>
                    config("app.url") .
                        "/api/v1/callback/" .
                        $request->order_number,
                    "merchant" => $thirdchannel->thirdChannel->merchant_id,
                    "key" => $thirdchannel->thirdChannel->key,
                    "key2" => $thirdchannel->thirdChannel->key2,
                    "key3" => $thirdchannel->thirdChannel->key3,
                    "key4" => $thirdchannel->thirdChannel->key4,
                    "proxy" => $thirdchannel->thirdChannel->proxy,
                    "request" => $request,
                    'order_number' => $orderNumber,
                    'system_order_number' => $transaction->system_order_number,
                ];

                if (property_exists($api, "rematchUrl")) {
                    $data["rematchUrl"] = preg_replace("/{$url[1]}/", $thirdchannel->thirdChannel->custom_url, $api->rematchUrl);
                }

                $return_data = $api->sendDeposit($data);

                //送單成功
                if ($return_data["success"]) {
                    $to = $transaction->to_channel_account;
                    $to["thirdchannel_cashier_url"] =
                        $return_data["data"]["pay_url"] ?? "";
                    $to['receiver_account'] = $return_data["data"]["receiver_account"] ?? "";
                    $to['receiver_name'] = $return_data["data"]["receiver_name"] ?? "";
                    $to['receiver_bank_name'] = $return_data["data"]["receiver_bank_name"] ?? "";
                    $to['receiver_bank_branch'] = $return_data["data"]["receiver_bank_branch"] ?? "";

                    $transaction->update([
                        "status" => Transaction::STATUS_THIRD_PAYING,
                        "thirdchannel_id" => $thirdchannel["thirdchannel_id"],
                        "to_channel_account" => $to,
                        "note" =>
                        $return_data["data"]["note"] ?? $transaction->note,
                        "matched_at" => now(),
                    ]);

                    $transactionFactory->createPaufenTransactionFees(
                        $transaction->refresh(),
                        $merchantUserChannel->channelGroup
                    );

                    $noteEnable = $transaction->channel->note_enable;

                    $cashierUrl = $thirdchannel->thirdChannel->use_third_cashier_url ? $to["thirdchannel_cashier_url"] : urldecode(
                        route(
                            "api.v1.cashier",
                            $transaction->system_order_number
                        )
                    );

                    return \App\Http\Resources\ThirdParty\Transaction::make(
                        $transaction
                    )
                        ->withMatchedInformation([
                            "casher_url" => $cashierUrl,
                            "receiver_account" =>
                            $return_data["data"]["receiver_account"] ?? "",
                            "receiver_name" =>
                            $return_data["data"]["receiver_name"] ?? "",
                            "receiver_bank_name" =>
                            $return_data["data"]["receiver_bank_name"] ??
                                "",
                            "receiver_bank_branch" =>
                            $return_data["data"]["receiver_bank_branch"] ??
                                "",
                            "note" => $noteEnable ? $transaction->note : "",
                        ])
                        ->additional([
                            "http_status_code" => Response::HTTP_CREATED,
                            "message" => __('common.Match successful'),
                        ])
                        ->response()
                        ->setStatusCode(Response::HTTP_OK);
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
            if (!$isLocalUserChannelAccount && $merchant->third_channel_enable && $merchant->include_self_providers) {
                // 嘗試匹配本地碼商
                goto LocalUserChannelAccount;
            }
            $transaction->update([
                "status" => Transaction::STATUS_MATCHING_TIMED_OUT,
            ]);

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

            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" =>
                ThirdPartyResponseUtil::ERROR_CODE_NO_AVAILABLE_USER_CHANNEL_ACCOUNT_FOR_TRANSACTION,
                "message" => __('common.Match timeout, please change amount and retry'),
            ]);
        }

        // 若匹配次數用完，但沒有匹配到收款碼才會執行到這邊，就認定此訂單為超時
        $transaction->update([
            "status" => Transaction::STATUS_MATCHING_TIMED_OUT,
        ]);

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

        return response()->json([
            "http_status_code" => Response::HTTP_BAD_REQUEST,
            "error_code" =>
            ThirdPartyResponseUtil::ERROR_CODE_NO_AVAILABLE_USER_CHANNEL_ACCOUNT_FOR_TRANSACTION,
            "message" => "匹配超时，请更换金额重新发起",
        ]);
    }

    private function blockBusyPaying(
        Request $request,
        FeatureToggleRepository $featureToggleRepository
    ): ?JsonResponse {
        // 1. Feature toggle 检查
        if (!$featureToggleRepository->enabled(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT)) {
            return null;
        }

        $transactionCreationRateLimitCount = max(
            $featureToggleRepository->valueOf(
                FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT,
                5
            ),
            1
        );

        $clientIp = $request->input("client_ip");

        // 2. 输入验证
        if (empty($clientIp) || !filter_var($clientIp, FILTER_VALIDATE_IP)) {
            return null;
        }

        // 3. 缓存键
        $cacheKey = "rate_limit:transaction:{$clientIp}";

        // 4. 带缓存的优化查询 - 推荐方案
        $count = Cache::remember($cacheKey, 60, function () use ($clientIp, $transactionCreationRateLimitCount) {
            // 只查询到限制数量，避免全表扫描
            return Transaction::where("client_ipv4", ip2long($clientIp))
                ->whereNotIn("status", [
                    Transaction::STATUS_MATCHING,
                    Transaction::STATUS_MATCHING_TIMED_OUT,
                ])
                ->where("created_at", ">", now()->subMinutes(10))
                ->limit($transactionCreationRateLimitCount + 1) // 只需要知道是否超过限制
                ->count();
        });

        // 5. 速率限制检查
        if ($count >= $transactionCreationRateLimitCount) {
            // 6. 记录日志
            Log::warning('Transaction rate limit exceeded', [
                'client_ip' => $clientIp,
                'count' => $count,
                'limit' => $transactionCreationRateLimitCount,
            ]);

            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" => ThirdPartyResponseUtil::ERROR_CODE_BUSY_PAYING,
                "message" => __('common.Please do not submit transactions too frequently'),
            ]);
        }

        return null;
    }

    /**
     * @param  User  $merchant
     * @param  Request  $request
     * @param  Channel  $channel
     * @param  TransactionFactory  $transactionFactory
     * @return Transaction
     */
    private function createTransaction(
        User $merchant,
        Request $request,
        Channel $channel,
        TransactionFactory $transactionFactory
    ): Transaction {
        $transactionFactory = $transactionFactory
            ->clientIpv4($request->input("client_ip"))
            ->amount($request->input("amount"))
            ->orderNumber($request->input("order_number"))
            ->notifyUrl($request->input("notify_url"))
            ->realName($request->input("real_name"));

        if ($channel->code == Channel::CODE_USDT) {
            //取得USDT最新汇率
            $usdtUtil = app(UsdtUtil::class);

            $binanceUsdtRate = $usdtUtil->getRate()["rate"];
            $usdtRate = $request->input("usdt_rate", $binanceUsdtRate);
            $transactionFactory = $transactionFactory->usdtRate(
                $usdtRate,
                $binanceUsdtRate
            );
        }

        if ($channel->code == Channel::CODE_DC_BANK) {
            $transactionFactory = $transactionFactory->toData([
                "bank_name" => $request->bank_name,
            ]);
        }

        if ($request->has("return_url")) {
            $transactionFactory = $transactionFactory->toData([
                "return_url" => $request->return_url,
            ]);
        }

        if ($channel->note_enable) {
            if (
                $channel->note_type ||
                $channel->code == Channel::CODE_RE_ALIPAY
            ) {
                // 因為管端無法設定祝福語只能設定附言，但支付寶口紅包的祝福語必要所以寫死
                $transactionFactory->note(
                    $this->transactionNoteUtil->randomNote(
                        $request->input("amount"),
                        $channel
                    )
                );
            }
        }

        if (
            $channel->floating_enable
            //&& fmod($request->amount, 1) === 0.0
        ) {
            $floatingAmount = $this->floatingAmount(
                $request->amount,
                $channel->floating
            );

            if (
                !$this->featureToggleRepository->enabled(
                    FeatureToggle::MAX_AMOUNT_TO_START_FLOATING
                )
            ) {
                $transactionFactory->floatingAmount($floatingAmount);
            } else {
                if (
                    $this->bcMath->lte(
                        $request->amount,
                        $this->featureToggleRepository->valueOf(
                            FeatureToggle::MAX_AMOUNT_TO_START_FLOATING,
                            "2000"
                        )
                    )
                ) {
                    $transactionFactory->floatingAmount($floatingAmount);
                }
            }
        }

        $transaction = $transactionFactory->paufenTransactionTo(
            $merchant,
            $channel
        );

        if ($channel->order_timeout_enable) {
            MarkPaufenTransactionMatchingTimedOut::dispatch(
                $transaction
            )->delay(now()->addSeconds($channel->order_timeout));
        }

        return $transaction;
    }

    private function floatingAmount($originalAmount, $maxFloating): string
    {
        if ($maxFloating == 0) {
            return $originalAmount;
        }

        // 決定步進值
        $step = 0.01;  // 固定使用 0.01 作為最小單位

        // 取得最大值的絕對值
        $absMax = $this->bcMath->abs($maxFloating);

        // 根據 maxFloating 的正負決定範圍
        if ($maxFloating > 0) {
            // 正數範圍：0.01 到 maxFloating
            $availableFloatings = range($step, $absMax, $step);
        } else {
            // 負數範圍：-0.01 到 maxFloating
            $availableFloatings = range(-$step, -$absMax, -$step);
        }

        // 從可用範圍中隨機選擇一個值
        $randomFloat = count($availableFloatings) > 0 ? Arr::random($availableFloatings) : 0;

        // 使用 bcMath 進行精確計算
        return $this->bcMath->add($originalAmount, (string)$randomFloat);
    }

    public function callback(
        $order_number,
        Request $request,
        TransactionUtil $transactionUtil
    ) {
        $transaction = Transaction::where('order_number', $order_number)
            ->whereIn('type', [
                Transaction::TYPE_PAUFEN_TRANSACTION,
                Transaction::TYPE_NORMAL_WITHDRAW,
            ])
            ->first();

        if (!$transaction) {
            $transaction = Transaction::where('system_order_number', $order_number)
                ->whereIn('type', [
                    Transaction::TYPE_PAUFEN_TRANSACTION,
                    Transaction::TYPE_NORMAL_WITHDRAW,
                ])
                ->firstOrFail();
        }

        Log::debug(self::class . " callback", ["input" => $request->all()]);

        if (
            !$transaction->thirdchannel_id &&
            (str_contains($transaction->order_number, "test") || str_contains($transaction->system_order_number, "test"))
        ) {
            return "success";
        }

        $thirdChannel = ThirdChannel::where(
            "id",
            $transaction->thirdchannel_id
        )->firstOrFail();
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
                    return new JsonResponse(
                        [
                            "message" => __('common.IP not whitelisted'),
                        ],
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }
            $returnCallback = $api->callback(
                $request,
                $transaction,
                $thirdChannel
            );

            Log::debug($path . " callback", ["result" => $returnCallback]);

            if (isset($returnCallback["success"])) {
                $transactionUtil->markAsSuccess($transaction, null, true, false, false);
                return isset($returnCallback["resBody"])
                    ? new JsonResponse($returnCallback["resBody"])
                    : ($api->success ?? "SUCCESS");
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
                    return new JsonResponse($returnCallback["resBody"], $statusCode);
                }
                return $api->success ?? "SUCCESS";
            }
            return new JsonResponse(
                [
                    "message" => $returnCallback["error"],
                ],
                Response::HTTP_BAD_REQUEST
            );
        } else {
            if (isset($returnCallback["resBody"])) {
                return new JsonResponse($returnCallback["resBody"]);
            }
            return $api->success ?? "SUCCESS";
        }
    }
}

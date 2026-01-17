<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ThirdParty\UserChannelAccountMatching;
use App\Http\Controllers\ThirdParty\UserChannelMatching;
use App\Jobs\MarkPaufenTransactionMatchingTimedOut;
use App\Jobs\MarkPaufenTransactionPayingTimedOut;
use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Models\TransactionNote;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\TransactionFactory;
use App\Utils\TransactionNoteUtil;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use App\Utils\UserChannelAccountUtil;
use App\Models\BannedIp;
use App\Models\BannedRealname;
use Endroid\QrCode\Builder\Builder as QrBuilder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use tttran\viet_qr_generator\Generator;
use App\Utils\ThirdPartyResponseUtil;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use App\Utils\UsdtUtil;

//四方
use App\Models\ThirdChannel;
use App\Models\MerchantThirdChannel;

class CreateTransactionController extends Controller
{
    use UserChannelAccountMatching, UserChannelMatching;

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
        NotificationUtil        $notificationUtil,
        BCMathUtil              $bcMath,
        FeatureToggleRepository $featureToggleRepository,
        TransactionNoteUtil     $transactionNoteUtil
    )
    {
        $this->notificationUtil = $notificationUtil;
        $this->bcMath = $bcMath;
        $this->featureToggleRepository = $featureToggleRepository;
        $this->transactionNoteUtil = $transactionNoteUtil;
    }

    /**
     * Handle the incoming request.
     *
     * @param Request $request
     * @param TransactionFactory $transactionFactory
     * @param BCMathUtil $bcMath
     * @param WalletUtil $wallet
     * @param FeatureToggleRepository $featureToggleRepository
     * @param WhitelistedIpManager $whitelistedIpManager
     * @return Application|Factory|View
     * @throws ValidationException
     */
    public function __invoke(Request $request)
    {
        $featureToggleRepository = $this->featureToggleRepository;
        $bcMath = $this->bcMath;
        $wallet = new WalletUtil($bcMath);
        $transactionFactory = app(TransactionFactory::class);
        $whitelistedIpManager = new WhitelistedIpManager();

        if (env("APP_ENV") != "local") {
            URL::forceScheme("https");
        }

        // 會員提單時 notify_url 及 real_name 有可能經過 urlencode，所以先 urldecode
        $request->merge(["notify_url" => urldecode($request->notify_url)]);
        if ($request->real_name) {
            $request->merge(["real_name" => urldecode($request->real_name)]);
        }

        $this->validate($request, [
            "channel_code" => "required",
            "username" => "required",
            "amount" => "required",
        ]);

        if (
            $request->channel_code == Channel::CODE_DC_BANK &&
            !$request->has("bank_name")
        ) {
            return response()->json([
                "http_status_code" => Response::HTTP_BAD_REQUEST,
                "error_code" => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                "message" => __('common.Missing parameter: :attribute', ['attribute' => 'bank_name']),
            ]);
        }

        /** @var Channel|null $channel */
        $channel = Channel::where("code", $request->channel_code)->first();
        $country = $channel->country;
        $code = strtolower($channel->code);
        $version = $channel->cashier_version;
        $clientIp = $request->input(
            "client_ip",
            $whitelistedIpManager->extractIpFromRequest($request)
        );

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
            return $this->errorViewWith(__('common.No matching channel'));
        }

        if ($channel->status !== Channel::STATUS_ENABLE) {
            return $this->errorViewWith(__('common.Channel under maintenance'));
        }

        /** @var User|null $merchant */
        $merchant = User::where([
            ["username", $request->username],
            ["role", User::ROLE_MERCHANT],
        ])->first();

        if (!$merchant) {
            return $this->errorViewWith(__('common.User not found'));
        }

        if ($merchant->disabled()) {
            return $this->errorViewWith(__('common.Account deactivated'));
        }

        $params = $request->except("sign");
        ksort($params);

        $sign = md5(
            urldecode(
                http_build_query($params) .
                "&secret_key=" .
                $merchant->secret_key
            )
        );

        unset($params["real_name"]);
        $noRealNameSign = md5(
            urldecode(
                http_build_query($params) .
                "&secret_key=" .
                $merchant->secret_key
            )
        );

        if (!in_array(strtolower($request->sign), [$sign, $noRealNameSign])) {
            return $this->errorViewWith(__('common.Signature error'));
        }

        if (!$merchant->transaction_enable) {
            return $this->errorViewWith(__("user.Transaction disabled"));
        }

        $totalCost = $bcMath->sum([
            $request->input("amount"),
            $merchant->wallet->available_balance,
        ]);

        if (
            $merchant->balance_limit >= 1 &&
            $bcMath->lt($merchant->balance_limit, $totalCost)
        ) {
            return $this->errorViewWith(__('common.Balance exceeds limit, please withdraw first'));
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
            return $this->errorViewWith(__('common.Amount error'));
        }

        /** @var UserChannel $merchantUserChannel */
        [$merchantUserChannel, $channelAmount] = $this->findSuitableUserChannel(
            $merchant,
            $channel,
            $request->amount
        );

        if (!$merchantUserChannel || !$channelAmount) {
            return $this->errorViewWith(__('common.Channel not found'));
        }

        if (
            !is_null($merchantUserChannel->min_amount) &&
            $bcMath->gtZero($merchantUserChannel->min_amount) &&
            $bcMath->lt($request->amount, $merchantUserChannel->min_amount)
        ) {
            return $this->errorViewWith(
                __('transaction.Amount greater', ['amount' => $merchantUserChannel->min_amount])
            );
        }

        if (
            !is_null($merchantUserChannel->max_amount) &&
            $bcMath->gtZero($merchantUserChannel->max_amount) &&
            $bcMath->gt($request->amount, $merchantUserChannel->max_amount)
        ) {
            return $this->errorViewWith(
                __('transaction.Amount less', ['amount' => $merchantUserChannel->max_amount])
            );
        }

        if (
            $merchantUserChannel->real_name_enable &&
            $channel->real_name_enable &&
            !$request->filled("real_name")
        ) {
            if ($version && $version !== "v1") {
                return view(
                    "v1.transactions.{$country}.{$code}.{$version}.prepare-real-name",
                    [
                        "channel" => $channel,
                        "amount" => $request->amount,
                    ]
                );
            }
            return view("v1.transactions.{$country}.prepare-real-name", [
                "channel" => $channel,
                "amount" => $request->amount,
            ]);
        }

        $transaction = Transaction::where([
            ["to_id", $merchant->getKey()],
            ["type", Transaction::TYPE_PAUFEN_TRANSACTION],
            ["order_number", $request->order_number],
        ])->first();

        if (!$transaction) {
            if (
                $blockedView = $this->blockBusyPaying(
                    $request,
                    $whitelistedIpManager,
                    $featureToggleRepository
                )
            ) {
                $this->notificationUtil->notifyBusyPayingBlocked(
                    $merchant,
                    $request->order_number,
                    $whitelistedIpManager->extractIpFromRequest($request),
                    $request->amount
                );

                return $blockedView;
            }

            $transaction = $this->createTransaction(
                $merchant,
                $request,
                $channel,
                $transactionFactory,
                $merchantUserChannel
            );
        }

        // 在匹配之前先確認有沒有匹配超時
        if (
            $transaction->matching() &&
            $transaction->shouldMatchingTimedOut()
        ) {
            $updatedRow = Transaction::where([
                ["id", $transaction->getKey()],
                ["type", Transaction::TYPE_PAUFEN_TRANSACTION],
                ["status", Transaction::STATUS_MATCHING],
            ])->update(["status" => Transaction::STATUS_MATCHING_TIMED_OUT]);

            throw_if(
                $updatedRow > 1,
                new RuntimeException("Unexpected row being updated")
            );

            // 只有在成功更新要顯示匹配超時
            if ($updatedRow === 1 && $country != "vn") {
                if ($version && $version !== "v1") {
                    return view(
                        "v1.transactions.{$country}.{$code}.{$version}.matching-timed-out",
                        compact("transaction")
                    );
                }
                return view(
                    "v1.transactions.{$country}.matching-timed-out",
                    compact("transaction")
                );
            }
        }

        // 三方已匹配直接返回匹配後的內容
        if (
            $transaction->thirdChannelPaying() &&
            $transaction->to_channel_account["thirdchannel_cashier_url"] ??
            ""
        ) {
            return $this->responseOf($transaction, $featureToggleRepository);
        }

        // 已匹配直接返回匹配後的內容
        if ($transaction->paying()) {
            return $this->responseOf($transaction, $featureToggleRepository);
        }

        if ($country != "vn") {
            if ($version && $version !== "v1") {
                // todo 補上所有狀態進來這個頁面的邏輯
                if ($transaction->success()) {
                    return view(
                        "v1.transactions.{$country}.{$code}.{$version}.paying-success",
                        compact("transaction")
                    );
                }

                if (
                    $transaction->matchingTimedOut() ||
                    $transaction->thirdChannelPaying()
                ) {
                    return view(
                        "v1.transactions.{$country}.{$code}.{$version}.matching-timed-out",
                        compact("transaction")
                    );
                }

                if ($transaction->payingTimedOut()) {
                    return view(
                        "v1.transactions.{$country}.{$code}.{$version}.paying-timed-out",
                        compact("transaction")
                    );
                }

                if (!$transaction->matching()) {
                    return view(
                        "v1.transactions.{$country}.{$code}.{$version}.please-try-later",
                        compact("transaction")
                    );
                }
            }
            // todo 補上所有狀態進來這個頁面的邏輯
            if ($transaction->success()) {
                return view(
                    "v1.transactions.{$country}.paying-success",
                    compact("transaction")
                );
            }

            if (
                $transaction->matchingTimedOut() ||
                $transaction->thirdChannelPaying()
            ) {
                return view(
                    "v1.transactions.{$country}.matching-timed-out",
                    compact("transaction")
                );
            }

            if ($transaction->payingTimedOut()) {
                return view(
                    "v1.transactions.{$country}.paying-timed-out",
                    compact("transaction")
                );
            }

            if (!$transaction->matching()) {
                return view(
                    "v1.transactions.{$country}.please-try-later",
                    compact("transaction")
                );
            }
        } else {
            if ($transaction->matchingTimedOut()) {
                return $this->matchingView($transaction);
            }

            if (!$transaction->matching()) {
                return $this->responseOf(
                    $transaction,
                    $featureToggleRepository
                );
            }
        }

        $isLocalUserChannelAccount = false; # 是否已嘗試匹配本地碼商

        // 嘗試匹配
        if (
            !$merchant->third_channel_enable ||
            ($merchant->third_channel_enable &&
                $merchant->include_self_providers &&
                mt_rand(0, 1))
        ) {
            LocalUserChannelAccount: # 三方匹配失敗，嘗試本地碼商
            $isLocalUserChannelAccount = true;

            // 沒啟用三方 或者 啟用三方且共用本站碼商且50%
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
                // successfully matched
                if ($providerUserChannelAccount) {
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

                        Cache::put("users_{$userId}_new_transaction", true, 60);

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
                        Log::error($e, [
                            $transaction->system_order_number,
                            $transaction->order_number,
                        ]);
                    }
                }
            }
        }

        $transaction->refresh();

        //檢查是否為四方
        if ($merchant->third_channel_enable && $transaction->status == Transaction::STATUS_MATCHING) {
            if (
                !Redis::set(
                    "{$transaction->order_number}:lock",
                    1,
                    "EX",
                    300,
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

                $apiUrl = preg_replace(
                    "/{$url[1]}/",
                    $thirdchannel->thirdChannel->custom_url,
                    $api->depositUrl
                );

                $orderNumber = $thirdchannel->thirdChannel->enable_system_order_number ? $transaction->system_order_number : $request->order_number;

                $data = [
                    "url" => $apiUrl,
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
                    "client_ip" => $clientIp,
                    'order_number' => $orderNumber,
                    'system_order_number' => $transaction->system_order_number,
                ];

                if (property_exists($api, "rematchUrl")) {
                    $data["rematchUrl"] = preg_replace("/{$url[1]}/", $thirdchannel->thirdChannel->custom_url, $api->rematchUrl);
                }

                $return_data = $api->sendDeposit($data);
                //送單成功
                if ($return_data["success"]) {
                    $transaction->refresh();
                    if ($transaction->status == Transaction::STATUS_PAYING) {
                        // 已經有匹配到碼商，則直接返回
                        return $this->responseOf(
                            $transaction->refresh(),
                            $featureToggleRepository
                        );
                    }

                    $to = $transaction->to_channel_account;
                    $to["thirdchannel_cashier_url"] =
                        $return_data["data"]["pay_url"] ?? "";

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

                    return $this->responseOf(
                        $transaction->refresh(),
                        $featureToggleRepository
                    );
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

            if (!$transaction->thirdChannelPaying()) {
                if (!$isLocalUserChannelAccount && $merchant->third_channel_enable && $merchant->include_self_providers) {
                    // 嘗試匹配本地碼商
                    goto LocalUserChannelAccount;
                }
                // 没配到三方，才马上超时
                $transaction->update([
                    "status" => Transaction::STATUS_MATCHING_TIMED_OUT,
                ]);
            }
            if ($transaction->channel_code == Channel::CODE_MAYA) {
                return redirect(
                    env("ADMIN_URL") .
                    "/maya/fail/" .
                    $transaction->order_number
                );
            }
            if ($version && $version !== "v1") {
                return view(
                    "v1.transactions.{$country}.{$code}.{$version}.matching-timed-out",
                    compact("transaction")
                );
            }
            return view(
                "v1.transactions.{$country}.matching-timed-out",
                compact("transaction")
            );
        }

        return $this->matchingView($transaction);
    }

    private function errorViewWith(string $errorMessage)
    {
        return view("v1.transactions.error", compact("errorMessage"));
    }

    /**
     * @param Request $request
     * @param WhitelistedIpManager $whitelistedIpManager
     * @param FeatureToggleRepository $featureToggleRepository
     * @return View|false
     */
    private function blockBusyPaying(
        Request                 $request,
        WhitelistedIpManager    $whitelistedIpManager,
        FeatureToggleRepository $featureToggleRepository
    )
    {
        if (
            !$featureToggleRepository->enabled(
                FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT
            )
        ) {
            return false;
        }

        $transactionCreationRateLimitCount = max(
            $featureToggleRepository->valueOf(
                FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT,
                5
            ),
            1
        );

        $clientIp = $request->input(
            "client_ip",
            $whitelistedIpManager->extractIpFromRequest($request)
        );

        $count = Transaction::where("client_ipv4", ip2long($clientIp))
            ->whereNotIn("status", [
                Transaction::STATUS_MATCHING,
                Transaction::STATUS_MATCHING_TIMED_OUT,
            ])
            ->where("created_at", ">", now()->subMinutes(10))
            ->count();

        if ($count >= $transactionCreationRateLimitCount) {
            return $this->errorViewWith(__('common.Please do not submit transactions too frequently'));
        }
    }

    /**
     * @param User $merchant
     * @param Request $request
     * @param Channel $channel
     * @param TransactionFactory $transactionFactory
     * @param UserChannel $merchantUserChannel
     * @return Transaction
     */
    private function createTransaction(
        User               $merchant,
        Request            $request,
        Channel            $channel,
        TransactionFactory $transactionFactory
    ): Transaction
    {
        $transactionFactory = $transactionFactory
            ->clientIpv4(
                $request->input("client_ip", Arr::last(request()->ips()))
            )
            ->amount($request->amount)
            ->orderNumber($request->order_number)
            ->notifyUrl($request->notify_url)
            ->realName($request->real_name);

        if ($channel->code == Channel::CODE_USDT) {
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
                        $request->amount,
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

    private function responseOf(
        Transaction             $transaction,
        FeatureToggleRepository $featureToggleRepository
    )
    {
        $channel = $transaction->channel;
        $code = strtolower($channel->code);
        $country = $channel->country;
        $thirdchannel = $transaction->thirdchannel_id;
        $thirdchannel_url =
            $transaction->to_channel_account["thirdchannel_cashier_url"] ?? "";

        if ($thirdchannel && $thirdchannel_url && $transaction->thirdChannel->cashier_mode != 2) {
            return redirect($thirdchannel_url);
        }

        if (!$thirdchannel || $transaction->thirdChannel->cashier_mode == 2) {
            if ($channel->code == Channel::CODE_DC_BANK) {
                $bank = $transaction->to_channel_account["bank_name"];
                $code = "dc_" . strtolower($bank);
            } elseif (
                $channel->code === Channel::CODE_ALIPAY_BAC ||
                $channel->code === Channel::CODE_ALIPAY_SAC ||
                $channel->code === Channel::CODE_ALIPAY_COPY ||
                $channel->code === Channel::CODE_ALIPAY_GC
            ) {
                $code = strtolower(Channel::CODE_QR_ALIPAY);
            } elseif (in_array($channel->code, [Channel::CODE_WECHATPAY_BAC, Channel::CODE_WECHATPAY_SAC])) {
                $code = strtolower(Channel::CODE_QR_WECHATPAY);
            }

            $path = "v1.transactions.{$country}.{$code}";
            if ($channel->cashier_version != "") {
                $path = "{$path}.{$channel->cashier_version}";
            }
            $path = "{$path}.matched";

            if (!view()->exists($path)) {
                return abort(Response::HTTP_NOT_FOUND, "通道不存在");
            }

            $disableShowingAccount = $featureToggleRepository->enabled(
                FeatureToggle::DISABLE_SHOWING_ACCOUNT_ON_QR_ALIPAY_MATCHED_PAGE
            );
            $disableShowingQrCode = $featureToggleRepository->enabled(
                FeatureToggle::DISABLE_SHOWING_QR_CODE_ON_QR_ALIPAY_MATCHED_PAGE
            );
            $fromChannelAccount = $transaction->from_channel_account;

            if ($transaction->thirdChannel()->exists() && $transaction->thirdChannel->cashier_mode == 2) {
                $bankName = $transaction['to_channel_account']['receiver_bank_name'];
                $bankBranch = $transaction['to_channel_account']['receiver_bank_branch'];
                $bankAccount = $transaction['to_channel_account']['receiver_account'];
                $bankCardHolderName = $transaction['to_channel_account']['receiver_name'];
            } else {
                $bankName = data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_NAME);
                $bankBranch = data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_CARD_BRANCH);
                $bankAccount = data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME);
                $bankCardHolderName = data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER);
            }

            return view($path, [
                "channel" => $transaction->channel,
                "disableShowingAccount" => $disableShowingAccount,
                "disableShowingQrCode" => $disableShowingQrCode,
                "transaction" => $transaction,
                "qrCodePath" => $this->qrCodeS3Path($transaction),
                "note" => $transaction->note,
                "payingLimitEnabled" =>
                    $transaction->channel->transaction_timeout_enable,
                "payingLimitSeconds" =>
                    $transaction->channel->transaction_timeout,
                "redirectUrl" => data_get(
                    $fromChannelAccount,
                    UserChannelAccount::DETAIL_KEY_REDIRECT_URL,
                    $transaction->channel->scanQrcodeUrlScheme()
                ),
                "bankName" => $bankName,
                "bankBranch" => $bankBranch,
                "bankCardHolderName" => $bankAccount,
                "bankCardNumber" => $bankCardHolderName,
                "apiHost" => env("APP_URL"),
                "code" => $code
            ]);
        }
    }

    private function vietQR(Transaction $transaction)
    {
        $account = $transaction->from_channel_account;
        $amount = $transaction->floating_amount;
        $note = $transaction->note;

        switch ($account[UserChannelAccount::DETAIL_KEY_BANK_ID]) {
            case "VTB":
                $bank = "ICB";
                break;
            default:
                $bank = $account[UserChannelAccount::DETAIL_KEY_BANK_ID];
        }

        return "https://img.vietqr.io/image/{$bank}-{$account[UserChannelAccount::DETAIL_KEY_ACCOUNT]}-compact2.jpg?amount={$amount}&addInfo={$note}&accountName={$account[UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME]}";

        // 由於沒有持卡人姓名，故先棄用
        // $gen = Generator::create()
        //     ->bankId($transaction->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_NAME])
        //     ->accountNo($transaction->from_channel_account[UserChannelAccount::DETAIL_KEY_ACCOUNT])
        //     ->amount((int)$transaction->floating_amount)
        //     ->info($transaction->note)
        //     ->returnText(false)
        //     ->margin(-10)
        //     ->generate();
        // $result = json_decode($gen);

        // return $result;
    }

    private function qrCodeS3Path(Transaction $transaction)
    {
        $qrCodeFilePath = data_get(
            $transaction,
            "from_channel_account." .
            UserChannelAccount::DETAIL_KEY_PROCESSED_QR_CODE_FILE_PATH,
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

    private function matchingView(Transaction $transaction)
    {
        $channel = $transaction->channel;
        $code = strtolower($channel->code);
        $version = $channel->cashier_version;
        $country = $channel->country;

        if ($channel->code == Channel::CODE_DC_BANK) {
            $bank = $transaction->to_channel_account["bank_name"];
            $code = "dc_" . strtolower($bank);
        } elseif (
            $channel->code === Channel::CODE_ALIPAY_SAC ||
            $channel->code === Channel::CODE_ALIPAY_BAC ||
            $channel->code === Channel::CODE_ALIPAY_COPY ||
            $channel->code === Channel::CODE_ALIPAY_GC
        ) {
            $code = strtolower(Channel::CODE_QR_ALIPAY);
        }

        if (
            !view()->exists("v1.transactions.{$country}.{$code}.matching") &&
            !view()->exists(
                "v1.transactions.{$country}.{$code}.{$version}.matching"
            )
        ) {
            return abort(Response::HTTP_NOT_FOUND, "通道不存在");
        }

        if ($version) {
            return view(
                "v1.transactions.{$country}.{$code}.{$version}.matching",
                compact("transaction")
            );
        }

        return view(
            "v1.transactions.{$country}.{$code}.matching",
            compact("transaction")
        );
    }

    private function alipayBankToRedirectUrl(Transaction $transaction)
    {
        $schemeParameters = [
            "appId" => "09999988",
            "actionType" => "toCard",
            "sourceId" => "bill",
            "money" => $transaction->floating_amount,
            "amount" => $transaction->floating_amount,
            "orderSource" => "from",
            "buyId" => "auto",
        ];

        $finalParameters = [
            "scheme" =>
                "alipays://platformapi/startapp?" .
                http_build_query($schemeParameters),
        ];

        return "https://ds.alipay.com/?" . http_build_query($finalParameters);
    }

    private function textToQrCode(string $text)
    {
        $qrcode = QrBuilder::create()
            ->writer(new PngWriter())
            ->data($text)
            ->encoding(new Encoding("UTF-8"))
            ->margin(10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->size(500)
            ->build();

        return $qrcode->getString();
    }
}

<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Controllers\Controller;
use App\Jobs\MarkPaufenTransactionMatchingTimedOut;
use App\Jobs\MarkPaufenTransactionPayingTimedOut;
use App\Model\Channel;
use App\Model\ChannelAmount;
use App\Model\FeatureToggle;
use App\Model\Transaction;
use App\Model\User;
use App\Model\UserChannel;
use App\Model\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\ThirdPartyResponseUtil;
use App\Utils\TransactionFactory;
use App\Utils\TransactionNoteUtil;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

class InitTransactionController extends Controller
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
     * @return \App\Http\Resources\ThirdParty\Transaction|JsonResponse
     * @throws ValidationException
     */
    public function __invoke(
        Request $request,
        TransactionFactory $transactionFactory,
        BCMathUtil $bcMath,
        WalletUtil $wallet,
        FeatureToggleRepository $featureToggleRepository,
        WhitelistedIpManager $whitelistedIpManager
    ) {
        foreach (['channel_code', 'username', 'amount', 'notify_url', 'client_ip', 'sign'] as $requiredAttribute) {
            if (!$request->filled($requiredAttribute)) {
                return response()->json([
                    'http_status_code' => Response::HTTP_BAD_REQUEST,
                    'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                    'message'          => __('common.Missing parameter: :attribute', ['attribute' => $requiredAttribute]),
                ]);
            }
        }

        /** @var Channel|null $channel */
        $channel = Channel::where('code', $request->channel_code)->firstOrFail();

        if (!$channel) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_INVALID_CHANNEL_CODE,
                'message'          => __('common.No matching channel')
            ]);
        }

        if ($channel->status !== Channel::STATUS_ENABLE) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_CHANNEL_TEMPORARY_UNAVAILABLE,
                'message'          => __('common.Channel under maintenance')
            ]);
        }

        /** @var User|null $merchant */
        $merchant = User::where([
            ['username', $request->username],
            ['role', User::ROLE_MERCHANT]
        ])->first();

        if (!$merchant) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_USER_NOT_FOUND,
                'message'          => __('common.User not found')
            ]);
        }

        if ($merchant->disabled()) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_USER_NOT_FOUND,
                'message'          => __('common.Account deactivated')
            ]);
        }

        $parameters = $request->except('sign');

        ksort($parameters);

        $sign = md5(urldecode(http_build_query($parameters).'&secret_key='.$merchant->secret_key));

        if (strcasecmp($sign, $request->input('sign'))) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_INVALID_SIGN,
                'message'          => __('common.Signature error')
            ]);
        }

        if ($whitelistedIpManager->isNotAllowedToUseThirdPartyApi($merchant, $request)) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_INVALID_IP,
                'message'          => __('common.Please contact admin to add IP to whitelist'),
            ]);
        }

        if (!$merchant->transaction_enable) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_TRANSACTION_DISABLED,
                'message'          => __('user.Transaction disabled')
            ]);
        }

        $channelAmounts = ChannelAmount::where('channel_code', $channel->getKey())
            ->orderBy(DB::raw('max_amount - min_amount'))
            ->get();

        $channelAmount = $channelAmounts->filter(function ($channelAmount) use ($request) {
            return ($request->amount >= $channelAmount->min_amount && $request->amount <= $channelAmount->max_amount) ||
                   ($channelAmount->fixed_amount && in_array($request->amount, $channelAmount->fixed_amount));
        })->first();

        if (!$channelAmount) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_INVALID_AMOUNT,
                'message'          => __('common.Wrong amount, please change and retry')
            ]);
        }

        /** @var UserChannel $merchantUserChannel */
        [$merchantUserChannel, $channelAmount] = $this->findSuitableUserChannel($merchant, $channel, $request->amount);

        if (
            !is_null($merchantUserChannel->min_amount)
            && $bcMath->gtZero($merchantUserChannel->min_amount)
            && $bcMath->lt($request->amount, $merchantUserChannel->min_amount)
        ) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT,
                'message'          => __('transaction.Amount greater', ['amount' => $merchantUserChannel->min_amount])
            ]);
        }

        if (
            !is_null($merchantUserChannel->max_amount)
            && $bcMath->gtZero($merchantUserChannel->max_amount)
            && $bcMath->gt($request->amount, $merchantUserChannel->max_amount)
        ) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_INVALID_MAX_AMOUNT,
                'message'          => __('transaction.Amount less', ['amount' => $merchantUserChannel->max_amount])
            ]);
        }

        if ($merchantUserChannel->real_name_enable && $channel->real_name_enable && !$request->filled('real_name')) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                'message'          => __('common.Missing parameter: :attribute', ['attribute' => 'real_name']),
            ]);
        }

        $transaction = Transaction::where([
            ['to_id', $merchant->getKey()],
            ['type', Transaction::TYPE_PAUFEN_TRANSACTION],
            ['order_number', $request->order_number],
        ])->first();

        if ($transaction) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_DUPLICATE_ORDER_NUMBER,
                'message'          => __('common.Duplicate number'),
            ]);
        }

        if ($blockedResponse = $this->blockBusyPaying($request, $featureToggleRepository)) {
            $this->notificationUtil->notifyBusyPayingBlocked(
                $merchant, $request->order_number, $request->input('client_ip'), $request->amount
            );

            return $blockedResponse;
        }

        $transaction = $this->createTransaction($merchant, $request, $channel, $transactionFactory);

        // 嘗試匹配
        /** @var UserChannelAccount|null $providerUserChannelAccount */
        $providerUserChannelAccount = $this->findSuitableUserChannelAccount(
            $transaction, $channel, $merchantUserChannel, $channelAmount, $featureToggleRepository, $bcMath
        );

        if ($providerUserChannelAccount) {
            // successfully matched
            try {
                DB::transaction(function () use (
                    $transaction,
                    $transactionFactory,
                    $providerUserChannelAccount,
                    $bcMath,
                    $wallet,
                    $channel,
                    $merchantUserChannel
                ) {
                    $transaction = $transactionFactory->paufenTransactionFrom($providerUserChannelAccount,
                        $transaction);

                    $wallet->withdraw($transaction->fromWallet, $transaction->floating_amount,
                        $transaction->system_order_number, $transactionType='transaction');

                    if ($channel->transaction_timeout_enable) {
                        MarkPaufenTransactionPayingTimedOut::dispatch($transaction)->delay(now()->addSeconds($channel->transaction_timeout));
                    }
                });

                $userId = $providerUserChannelAccount->user_id;

                Cache::put("users_{$userId}_new_transaction", true, 60);

                return $this->responseOf($transaction->refresh(), $featureToggleRepository);
            } catch (Exception $e) {
                // 假設匹配到但因為 Race condition 或其他原因導致寫單失敗，讓使用者繼續重新匹配
                Log::error($e, [$transaction->system_order_number, $transaction->order_number]);
            }
        }

        return \App\Http\Resources\ThirdParty\Transaction::make($transaction)
            ->additional([
                'http_status_code' => Response::HTTP_ACCEPTED,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_PLEASE_RETRY_FOR_ANOTHER_MATCHING,
                'message'          => __('common.Please try again later'),
            ]);
    }

    /**
     * @param  Request  $request
     * @param  FeatureToggleRepository  $featureToggleRepository
     * @return JsonResponse|false
     */
    private function blockBusyPaying(
        Request $request,
        FeatureToggleRepository $featureToggleRepository
    ) {
        if (!$featureToggleRepository->enabled(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT)) {
            return false;
        }

        $transactionCreationRateLimitCount = max($featureToggleRepository->valueOf(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT,
            3), 1);

        $clientIp = $request->input('client_ip');
        $key = 'create-transactions-ip-'.$clientIp;
        $blockKey = 'block-'.$key;
        $countKey = 'count-'.$key;

        return Redis::funnel($key)->limit(1)->then(function () use (
            $blockKey,
            $countKey,
            $transactionCreationRateLimitCount
        ) {
            if (Cache::get($blockKey)) {
                return response()->json([
                    'http_status_code' => Response::HTTP_BAD_REQUEST,
                    'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_BUSY_PAYING,
                    'message'          => __('common.Please do not submit transactions too frequently'),
                ]);
            }

            $currentCount = 1;
            $countKeyAdded = Cache::add($countKey, $currentCount, now()->addSeconds(45));

            if (!$countKeyAdded) {
                $currentCount = Cache::increment($countKey);
            }

            if ($currentCount > $transactionCreationRateLimitCount) {
                Cache::add($blockKey, true, now()->addMinutes(3));
                Cache::forget($countKey);

                return response()->json([
                    'http_status_code' => Response::HTTP_BAD_REQUEST,
                    'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_BUSY_PAYING,
                    'message'          => __('common.Please do not submit transactions too frequently'),
                ]);
            }

            return false;
        }, function () {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_BUSY_PAYING,
                'message'          => '请勿频繁发起交易，请稍候再重新发起',
            ]);
        });
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
            ->clientIpv4($request->input('client_ip'))
            ->amount($request->input('amount'))
            ->orderNumber($request->input('order_number'))
            ->notifyUrl($request->input('notify_url'))
            ->realName($request->input('real_name'));

        if ($channel->note_enable && $channel->note_type) {
            $transactionFactory->note($this->transactionNoteUtil->randomNote($request->input('amount'), $channel));
        }

        if ($channel->floating_enable && fmod($request->amount, 1) === 0.0) {
            if (!$this->featureToggleRepository->enabled(FeatureToggle::MAX_AMOUNT_TO_START_FLOATING)) {
                $transactionFactory->floatingAmount($this->floatingAmount($request->amount, $channel->floating));
            } else {
                if ($this->bcMath->lte(
                    $request->amount,
                    $this->featureToggleRepository->valueOf(FeatureToggle::MAX_AMOUNT_TO_START_FLOATING, '2000')
                )) {
                    $transactionFactory->floatingAmount($this->floatingAmount($request->amount, $channel->floating));
                }
            }
        }

        $transaction = $transactionFactory->paufenTransactionTo($merchant, $channel);

        if ($channel->order_timeout_enable) {
            MarkPaufenTransactionMatchingTimedOut::dispatch($transaction)->delay(now()->addSeconds($channel->order_timeout));
        }

        return $transaction;
    }

    private function floatingAmount($originalAmount, $maxFloating): string
    {
        $availableFloatings = range(0.01, $this->bcMath->abs($maxFloating), 0.01);

        return $this->bcMath->sub($originalAmount, Arr::random($availableFloatings));
    }
}

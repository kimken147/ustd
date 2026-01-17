<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Controllers\Controller;
use App\Jobs\MarkPaufenTransactionPayingTimedOut;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\ThirdPartyResponseUtil;
use App\Utils\TransactionFactory;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RetryTransactionController extends Controller
{

    use UserChannelAccountMatching, UserChannelMatching, MatchedJsonResponse;

    public function __invoke(
        Request $request,
        WhitelistedIpManager $whitelistedIpManager,
        FeatureToggleRepository $featureToggleRepository,
        BCMathUtil $bcMath,
        TransactionFactory $transactionFactory,
        WalletUtil $wallet
    ) {
        $requiredAttributes = [
            'username',
            'order_number',
            'sign'
        ];

        foreach ($requiredAttributes as $requiredAttribute) {
            if (empty($request->$requiredAttribute)) {
                return response()->json([
                    'http_status_code' => Response::HTTP_BAD_REQUEST,
                    'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                    'message'          => __('common.Information is incorrect: :attribute', ['attribute' => $requiredAttribute]),
                ]);
            }
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
                'message'          => __('common.User not found'),
            ]);
        }

        $parameters = $request->except('sign');

        ksort($parameters);

        $sign = md5(urldecode(http_build_query($parameters) . '&secret_key=' . $merchant->secret_key));

        if (strcasecmp($sign, $request->sign)) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_INVALID_SIGN,
                'message'          => __('common.Signature error'),
            ]);
        }

        if ($whitelistedIpManager->isNotAllowedToUseThirdPartyApi($merchant, $request)) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_INVALID_IP,
                'message'          => __('common.Please contact admin to add IP to whitelist'),
            ]);
        }

        /** @var Transaction $transaction */
        $transaction = Transaction::where([
            'type'         => Transaction::TYPE_PAUFEN_TRANSACTION,
            'to_id'        => $merchant->getKey(),
            'order_number' => $request->input('order_number'),
        ])->first();

        if (!$transaction) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_TRANSACTION_NOT_FOUND,
                'message'          => __('common.Order not found'),
            ]);
        }

        if ($transaction->status === Transaction::STATUS_PAYING) {
            return $this->responseOf($transaction->refresh(), $featureToggleRepository);
        }

        if ($transaction->status === Transaction::STATUS_MATCHING_TIMED_OUT) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_NO_AVAILABLE_USER_CHANNEL_ACCOUNT_FOR_TRANSACTION,
                'message'          => __('common.Match timeout, please change amount and retry'),
            ]);
        }

        if ($transaction->status === Transaction::STATUS_PAYING_TIMED_OUT) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_PAYING_TIMED_OUT,
                'message'          => __('common.Payment timeout, please change amount and retry'),
            ]);
        }

        if ($transaction->status !== Transaction::STATUS_MATCHING) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_CURRENT_STATUS_CANNOT_RETRY,
                'message'          => __('common.Status cannot be retried'),
            ]);
        }

        /** @var UserChannel $merchantUserChannel */
        [$merchantUserChannel, $channelAmount] = $this->findSuitableUserChannel(
            $merchant,
            $transaction->channel,
            $transaction->floating_amount
        );

        // 嘗試匹配
        /** @var UserChannelAccount|null $providerUserChannelAccount */
        $providerUserChannelAccount = $this->findSuitableUserChannelAccount(
            $transaction,
            $transaction->channel,
            $merchantUserChannel,
            $channelAmount,
            $featureToggleRepository,
            $bcMath
        );

        $channel = $transaction->channel;

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
                    $transaction = $transactionFactory->paufenTransactionFrom(
                        $providerUserChannelAccount,
                        $transaction
                    );

                    $wallet->withdraw(
                        $transaction->fromWallet,
                        $transaction->floating_amount,
                        $transaction->order_number,
                        $transactionType = 'transaction'
                    );

                    if ($channel->transaction_timeout_enable) {
                        MarkPaufenTransactionPayingTimedOut::dispatch($transaction->id)->delay(now()->addSeconds($channel->transaction_timeout));
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

        return response()->json([
            'http_status_code' => Response::HTTP_ACCEPTED,
            'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_PLEASE_RETRY_FOR_ANOTHER_MATCHING,
            'message'          => __('common.Please try again later'),
        ]);
    }
}

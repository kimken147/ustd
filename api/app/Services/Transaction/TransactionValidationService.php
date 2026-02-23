<?php

namespace App\Services\Transaction;

use App\Models\BannedIp;
use App\Models\BannedRealname;
use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannel;
use App\Services\Transaction\DTO\CreateTransactionContext;
use App\Services\Transaction\Exceptions\TransactionValidationException;
use App\Services\Withdraw\DTO\ThirdPartyErrorResponse;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionValidationService
{
    public function __construct(
        private readonly BCMathUtil $bcMath,
        private readonly FeatureToggleRepository $featureToggleRepository,
        private readonly NotificationUtil $notificationUtil,
        private readonly WhitelistedIpManager $whitelistedIpManager,
    ) {}

    /**
     * @throws TransactionValidationException
     */
    public function validateAndGetChannel(CreateTransactionContext $context): Channel
    {
        $channel = Channel::where("code", $context->channelCode)->first();

        if (!$channel) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::channelNotFound());
        }

        if ($channel->status !== Channel::STATUS_ENABLE) {
            throw new TransactionValidationException(ThirdPartyErrorResponse::channelMaintenance());
        }

        return $channel;
    }

    /**
     * @throws TransactionValidationException
     */
    public function validateAndGetMerchant(CreateTransactionContext $context, Channel $channel): User
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
    public function validateSignature(CreateTransactionContext $context, User $merchant): void
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
    public function validateAndGetUserChannel(
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

    public function findSuitableUserChannel(User $merchant, Channel $channel, string $amount): array
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

    /**
     * @throws TransactionValidationException
     */
    public function checkRateLimit(CreateTransactionContext $context, User $merchant): void
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

    public function withSign(Collection $postData, User $merchant): Collection
    {
        $postData = $postData->sortKeys();

        return $postData->merge([
            'sign' => strtolower(md5(urldecode(http_build_query(array_filter($postData->toArray())) . '&secret_key=' . $merchant->secret_key)))
        ]);
    }
}

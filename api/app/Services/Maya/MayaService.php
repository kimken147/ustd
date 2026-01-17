<?php

namespace App\Services\Maya;

use App\Exceptions\PaymayaResponseError;
use App\Models\UserChannelAccount;
use App\Services\Maya\MayaLoginService;
use App\Services\Maya\PayMayaApiService;
use App\Services\Maya\LogService;
use Exception;
use Illuminate\Support\Facades\Log;

class MayaService
{
    private MayaLoginService $mayaLoginService;
    private PayMayaApiService $mayaApiService;

    public function __construct(
        MayaLoginService $mayaLoginService,
        PayMayaApiService $mayaApiService
    ) {
        $this->mayaLoginService = $mayaLoginService;
        $this->mayaApiService = $mayaApiService;
    }

    public function getChannelName(string $functionName)
    {
        return "maya_service_" . $functionName;
    }

    public function changePassword(
        string $password,
        string $newPassword,
        $accessToken,
        $appToken
    ) {
        $logService = new LogService(
            $this->getChannelName(__FUNCTION__),
            __FUNCTION__
        );
        $data = [
            "current_password" => $password,
            "new_password" => $newPassword,
        ];
        $result = $this->mayaLoginService->payMayaApiService->apiChangeAccountPassword(
            $accessToken,
            $appToken,
            $data
        );
        if (array_key_exists("error", $result)) {
            throw new PaymayaResponseError($logService->getErrorData($result));
        }
        $response = json_encode($result, JSON_UNESCAPED_UNICODE);
        return $response;
    }

    public function changeEmail(
        string $password,
        string $newEmail,
        string $accessToken,
        string $appToken
    ) {
        $logService = new LogService(
            $this->getChannelName(__FUNCTION__),
            __FUNCTION__
        );
        $data = [
            "password" => $password,
            "backup_identity" => ["type" => "email", "value" => $newEmail],
        ];

        $logService->writeRequestLog([], ["data" => $data]);

        $result = $this->mayaApiService->apiChangeAccountEmail(
            $accessToken,
            $appToken,
            $data
        );
        if (array_key_exists("error", $result)) {
            throw new PaymayaResponseError($logService->getErrorData($result));
        }
        $response = json_encode($result, JSON_UNESCAPED_UNICODE);
        $logService->writeResponseLog($response);
        return $response;
    }

    public function verifyEmail(string $accessToken, string $appToken)
    {
        $logService = new LogService(
            $this->getChannelName(__FUNCTION__),
            __FUNCTION__
        );
        $result = $this->mayaApiService->apiVerifyEmail(
            $accessToken,
            $appToken
        );
        if (array_key_exists("error", $result)) {
            throw new PaymayaResponseError($logService->getErrorData($result));
        }
        $response = json_encode($result, JSON_UNESCAPED_UNICODE);
        $logService->writeResponseLog($response);
        return $response;
    }

    public function userChannelAccountLogin(UserChannelAccount $account)
    {
        $detail = $account->detail;
        Log::info("UserChannelAccount: " . $account->account . " login.");
        try {
            $result = $this->mayaLoginService->apiLogin(
                $account->account,
                $detail["mpin"]
            );
            $detail["expiresChallengeId"] = $result["expiresChallengeId"];
            $detail["accessToken"] = $result["accessToken"];
            $account->update([
                "detail" => $detail,
            ]);
            $account->detail = array_merge($account->detail, $detail);
            return $account;
        } catch (Exception $e) {
            $message = $this->getMayaServiceExceptionMessage($e);
            Log::error(
                "UserChannelAccount: " . $account->account . "login fail.",
                compact("message")
            );
            throw $e;
        }
    }

    public function userChannelOtpLogin(
        UserChannelAccount $account,
        string $otp
    ) {
        $detail = $account["detail"];
        $expiresChallengeId = $detail["expiresChallengeId"];
        $accessToken = $detail["accessToken"];
        try {
            Log::info(
                "UserChannelAccount: " . $account->account . "otp login."
            );
            $result = $this->mayaLoginService->apiLoginNext(
                $expiresChallengeId,
                $otp,
                $accessToken
            );
            return $result;
        } catch (Exception $e) {
            $message = $this->getMayaServiceExceptionMessage($e);
            Log::error(
                "UserChannelAccount: " . $account->account . "otp login fail.",
                compact("message")
            );
            throw $e;
        }
    }

    public function syncAccount(
        string $userChannelAccountId,
        string $accessToken,
        string $appToken
    ) {
        $account = UserChannelAccount::find($userChannelAccountId);
        $detail = $account->detail;

        $balanceRes = $this->mayaApiService->apiGetAccountBalance(
            $accessToken,
            $appToken
        );
        Log::debug(
            "User account id: " . $account->id . " get balance",
            $balanceRes
        );
        $balance = data_get($balanceRes, "available_balance.value");
        Log::debug("balance: " . $balance);
        $account->updateBalanceByUser($balance);

        $limitRes = $this->mayaApiService->apiGetAccountLimit(
            $accessToken,
            $appToken
        );
        $dailyDepositLimit = floatval(
            data_get($limitRes, "daily.0.amount.limit")
        );
        $dailyDepositRemaining = floatval(
            data_get($limitRes, "daily.0.amount.remaining")
        );
        $dailyTransferLimit = floatval(
            data_get($limitRes, "daily.1.amount.limit")
        );
        $dailyTransferRemaing = floatval(
            data_get($limitRes, "daily.1.amount.remaining")
        );

        $monthlyDepositLimit = floatval(
            data_get($limitRes, "monthly.0.amount.limit")
        );
        $monthlyDepositRemaining = floatval(
            data_get($limitRes, "monthly.0.amount.remaining")
        );
        $monthlyTransferLimit = floatval(
            data_get($limitRes, "monthly.1.amount.limit")
        );
        $monthlyTransferRemaining = floatval(
            data_get($limitRes, "monthly.1.amount.remaining")
        );

        $limit = [
            "daily_limit" => $dailyDepositLimit,
            "daily_total" => $dailyDepositLimit - $dailyDepositRemaining,
            "withdraw_daily_limit" => $dailyTransferLimit,
            "withdraw_daily_total" =>
                $dailyTransferLimit - $dailyTransferRemaing,
            "monthly_limit" => $monthlyDepositLimit,
            "monthly_total" => $monthlyDepositLimit - $monthlyDepositRemaining,
            "withdraw_monthly_limit" => $monthlyTransferLimit,
            "withdraw_monthly_total" =>
                $monthlyTransferLimit - $monthlyTransferRemaining,
        ];

        $detail["sync_at"] = now();
        $detail["sync_status"] = "success";

        $account->update(array_merge($limit, ["detail" => $detail]));
        $account->refresh();
        return $account;
    }

    public function getMayaServiceExceptionMessage(PaymayaResponseError $e)
    {
        $error = json_decode($e->getMessage())->data->error;
        if (property_exists($error, "spiel")) {
            $message = $error->spiel;
            Log::error("Maya exeption", [
                "message" => $message,
            ]);
        } elseif (property_exists($error, "msg")) {
            $message = $error->msg;
            Log::error("Maya exeption", [
                "message" => $message,
            ]);
        } else {
            $message = "Unknown error.";
            Log::error($error, "Maya exception");
        }
        return $message;
    }
}

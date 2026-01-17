<?php

namespace App\Services\Maya;

use App\Exceptions\PaymayaResponseError;

class MayaLoginService
{
    public HeaderDataService $headerDataService;
    public PayMayaApiService $payMayaApiService;

    public function __construct(
        HeaderDataService $headerDataService,
        PayMayaApiService $payMayaApiService
    ) {
        $this->headerDataService = $headerDataService;
        $this->payMayaApiService = $payMayaApiService;
    }

    public function getChannelName(string $functionName)
    {
        return "login_service_" . $functionName;
    }

    public function apiLogin($phoneNumber, $password)
    {
        # 紀錄傳入 log
        $logService = new LogService(
            $this->getChannelName(__FUNCTION__),
            __FUNCTION__
        );
        $logService->writeRequestLog(
            [],
            ["phoneNumber" => $phoneNumber, "password" => $password]
        );

        $accessToken = $this->getAccessToken();
        $challengeId = $this->tryLogin($phoneNumber, $password, $accessToken);
        $expiresChallengeId = $this->mfaChallengesLoginStart(
            $phoneNumber,
            $challengeId,
            $accessToken
        );
        return [
            "expiresChallengeId" => $expiresChallengeId,
            "accessToken" => $accessToken,
        ];
    }

    public function apiLoginNext($expiresChallengeId, $otp, $accessToken)
    {
        # 紀錄傳入 log
        $logService = new LogService(
            $this->getChannelName(__FUNCTION__),
            __FUNCTION__
        );
        $logService->writeRequestLog(
            [],
            ["expiresChallengeId" => $expiresChallengeId]
        );

        $loginChallengeId = $this->mfaChallengesLoginVerify(
            $expiresChallengeId,
            $otp,
            $accessToken
        );
        $profile = $this->challengesLogin($loginChallengeId, $accessToken);

        $logService->writeResponseLog($profile);
        return $profile;
    }

    public function challengesLogin(string $challengeId, string $accessToken)
    {
        # 紀錄傳入 log
        $logService = new LogService(
            $this->getChannelName(__FUNCTION__),
            __FUNCTION__
        );
        $logService->writeRequestLog([], ["challengeId" => $challengeId]);

        $data = [
            "mfa" => [
                "challenge_id" => $challengeId,
            ],
            "source" => "android",
        ];
        $result = $this->payMayaApiService->sessions($accessToken, $data);
        if (
            !array_key_exists("account_status", $result) ||
            !array_key_exists("token", $result) ||
            $result["kyc_level"] == 0 ||
            $result["account_status"] != "ACTIVE"
        ) {
            throw new PaymayaResponseError($logService->getErrorData($result));
        }
        $logService->writeResponseLog($result);
        return $result;
        /*
            {
                "profile_id": "776217982795",
                "profile": { "first_name": "Rizele", "middle_name": "Ramos", "last_name": "Siron", "birth_date": "2000-08-14", "present_address": { "line1": "Lt26 Blk8 Green Mark Homes 2", "locality": "Malagasang 2-B", "city": "Imus", "state": "Cavite", "zip_code": "4103", "country": "PH" } },
                "backup_identity": { "type": "email", "value": "zelesiron22@gmail.com", "is_verified": false },
                "identities": [{ "type": "msisdn", "value": "+639366642409", "verified": true, "registration_id": "N/A" }],
                "fund_sources": [{ "status": "basic", "name": "Maya", "type": "virtual", "id": "db118bd9-364b-42ab-8ab0-3bcce8e5fa2b", "transaction_enabled": true, "card_profile": { "scheme": "VISA", "brand": "PAYMAYA" } }],
                "type": { "id": "CONSUMER_UPGRADED", "name": "Upgraded Consumer", "description": "Account with KYC 1", "region": { "regionId": "PH", "name": "PHILIPPINES", "locale": "EN" }, "currency": "PHP", "status": "ACTIVE" },
                "status": "funded",
                "account_status": "ACTIVE",
                "pep_status": false,
                "physical_card": "not_activated",
                "can_link_card": true,
                "network": "default",
                "has_gov_id": true,
                "kyc_level": "1",
                "kyc": "kyc1",
                "token": "vZVDSCQyNmaPxYu39VQdd67xPgcjgDFUY3vU3Mom5RcCCyYyi0oaoWtSASfPgBhxkGbbGgMTwrNqqUhwQH4KkdyCjmmZk6jW7FtkUO0r8vvZhllrnTlXjpv6p3exVeTUNN4n8Pxml7DAtXk7Doma4SQ9M0czGGB+d+q8YiG7zCfaalsycqT97yrSsmXa",
                "privacy_policy": { "status": "accepted" },
                "device_token": "52078528-e985-4278-b823-2e61e9f628e4",
                "can_be_referred": false,
                "wallet_id": "639877b2-8561-4c24-988e-fd818b4397ee",
                "re_kyc_status": "none",
                "re_kyc_reason": null
            }
        */
    }

    public function mfaChallengesLoginVerify(
        string $challengeId,
        string $otp,
        string $accessToken
    ) {
        # 紀錄傳入 log
        $logService = new LogService(
            $this->getChannelName(__FUNCTION__),
            __FUNCTION__
        );
        $logService->writeRequestLog([], ["challengeId" => $challengeId]);

        $data = [
            "method" => "OTP",
            "params" => [
                "otp" => $otp,
            ],
        ];
        $result = $this->payMayaApiService->challengesLoginVerify(
            $accessToken,
            $data,
            $challengeId
        );
        if (
            !array_key_exists("challenge_id", $result) ||
            !array_key_exists("status", $result) ||
            $result["status"] != "SUCCESS"
        ) {
            throw new PaymayaResponseError($logService->getErrorData($result));
        }
        $logService->writeResponseLog($result);
        return $result["challenge_id"];
    }

    public function mfaChallengesLoginStart(
        string $phoneNumber,
        string $challengeId,
        string $accessToken
    ) {
        # 紀錄傳入 log
        $logService = new LogService(
            $this->getChannelName(__FUNCTION__),
            __FUNCTION__
        );
        $logService->writeRequestLog(
            [],
            ["phoneNumber" => $phoneNumber, "challengeId" => $challengeId]
        );

        $data = [
            "method" => "OTP",
            "params" => [
                "value" => $this->headerDataService->helperService->normalizePhoneNumber(
                    $phoneNumber
                ),
                "type" => "msisdn",
            ],
        ];
        $result = $this->payMayaApiService->challengesLoginStart(
            $accessToken,
            $data,
            $challengeId
        );
        if (!array_key_exists("challenge_id", $result)) {
            throw new PaymayaResponseError($logService->getErrorData($result));
        }
        $logService->writeResponseLog($result);
        return $result["challenge_id"];
    }

    public function tryLogin(
        string $phoneNumber,
        string $password,
        string $accessToken
    ) {
        # 紀錄傳入 log
        $logService = new LogService(
            $this->getChannelName(__FUNCTION__),
            __FUNCTION__
        );
        $logService->writeRequestLog(
            [],
            ["phoneNumber" => $phoneNumber, "password" => $password]
        );

        $data = [
            "password" => $password,
            "identity" => [
                "value" => $this->headerDataService->helperService->normalizePhoneNumber(
                    $phoneNumber
                ),
                "type" => "msisdn",
            ],
            "source" => $this->headerDataService->helperService->getPlatformType(),
        ];
        $result = $this->payMayaApiService->sessions($accessToken, $data);
        if (
            !array_key_exists("error", $result) ||
            !array_key_exists("code", $result["error"]) ||
            $result["error"]["code"] != -384
        ) {
            throw new PaymayaResponseError($logService->getErrorData($result));
        }
        $logService->writeResponseLog($result);
        return $result["meta"]["challenge_id"];
    }

    public function getAccessToken()
    {
        # 紀錄傳入 log
        $logService = new LogService(
            $this->getChannelName(__FUNCTION__),
            __FUNCTION__
        );
        $logService->writeRequestLog([]);

        $result = $this->payMayaApiService->accessToken();
        if (
            !array_key_exists("token_type", $result) ||
            $result["token_type"] != "Bearer" ||
            !array_key_exists("access_token", $result)
        ) {
            throw new PaymayaResponseError($logService->getErrorData($result));
        }
        $logService->writeResponseLog($result);
        return $result["access_token"];
    }

    public function createP2pTransfer(
        $recipient,
        $amount,
        $accessToken,
        $appToken,
        $name = null
    ) {
        # 紀錄傳入 log
        $logService = new LogService(__CLASS__, __FUNCTION__);
        $logService->writeRequestLog(
            [],
            ["recipient" => $recipient, "amount" => $amount]
        );

        $data = [
            "recipient" => [
                "value" => $this->payMayaApiService->headerDataService->helperService->normalizePhoneNumber(
                    $recipient
                ),
                "type" => "PAYMAYA",
            ],
            "amount" => [
                "currency" => "PHP",
                "value" => $amount,
            ],
            "message" => $name ?? "Test",
        ];

        $result = $this->payMayaApiService->createP2pTransfer(
            $accessToken,
            $appToken,
            $data
        );
        if (
            !array_key_exists("transfer_token", $result) ||
            !array_key_exists("id", $result["transfer_token"])
        ) {
            throw new PaymayaResponseError($logService->getErrorData($result));
        }
        $response = json_encode($result, JSON_UNESCAPED_UNICODE);
        $logService->writeResponseLog($response);

        return $result["transfer_token"]["id"];
    }

    public function executeP2pTransfer($transferId, $accessToken, $appToken)
    {
        # 紀錄傳入 log
        $logService = new LogService(__CLASS__, __FUNCTION__);
        $logService->writeRequestLog([], ["transferId" => $transferId]);

        $result = $this->payMayaApiService->executeP2pTransfers(
            $accessToken,
            $appToken,
            $transferId
        );
        if (
            array_key_exists("error", $result) ||
            !(
                array_key_exists("transfer_token", $result) &&
                $result["transfer_token"]["status"] === "approved"
            )
        ) {
            throw new PaymayaResponseError($logService->getErrorData($result));
        }
        $response = json_encode($result, JSON_UNESCAPED_UNICODE);
        $logService->writeResponseLog($response);
        return $result;
    }
}

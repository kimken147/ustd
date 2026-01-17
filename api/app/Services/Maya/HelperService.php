<?php

namespace App\Services\Maya;

use Carbon\Carbon;
use Illuminate\Support\Str;

class HelperService
{
    public string $channel;

    public function getPlatformType()
    {
        return 'android';
    }

    public function getApkVersion()
    {
        return '2.89.0';
    }

    public function getEncryptedClient()
    {
        return 'paymaya-android';
    }

    public function getEncryptedSecret()
    {
        return 'A519U4Pv1jVDsm6sM8rNaGxd9OKRQ7PjOLmBUOFnMm0Ygn09DDO7gr9pBvKio7jkJnJidJJVPWOa84Lhpbvm1w';
    }

    public function getEncryptedFinal()
    {
        return 'HmacSHA256';
    }

    public function getEncryptedRequest()
    {
        return 'd89ff88567354718ba2b66184bb74dd8';
    }

    public function getEncryptedPin()
    {
        return '2C:FE:CF:96:1E:FD:FE:0C:EC:26:81:B5:97:9B:E0:E5:3F:AE:1C:49:9F:BB:5C:21:72:9B:65:14:80:DE:FB:E7';
    }

    public function getUserAgent()
    {
        return 'ZenFone Go (ZB552KL)(LOLLIPOP_MR1 6.0.1)';
    }

    public function getEncodedUserAgent()
    {
        return 'WmVuRm9uZSBHbyAoWkI1NTJLTCkoTE9MTElQT1BfTVIxIDYuMC4xKQ';
    }

    public function getRequestHeaders()
    {
        $time = Carbon::now()->getPreciseTimestamp(3);
        $requestToken = hash_hmac('sha256', $this->getPlatformType() . $time . $this->getUserAgent(), $this->getEncryptedRequest());
        return array(
            'x-request-timestamp'   => $time,
            'x-request-token'       => $requestToken
        );
    }

    public function getClientId()
    {
        return (string) Str::uuid();
    }

    public function getSessionId()
    {
        return preg_replace('/[^a-zA-Z0-9]/', '', (string) Str::uuid());
    }

    public function getRequestId()
    {
        return (string) Str::uuid();
    }

    public function normalizePhoneNumber($phoneNumber)
    {
        if (strpos($phoneNumber, '0', 0) === 0) {
            $phoneNumber = '+63' . substr($phoneNumber, 1);
        }
        return $phoneNumber;
    }
}

<?php

namespace App\Utils;

class SignatureCalculator
{
    public static function calculate(array $params, string $secretKey): string
    {
        ksort($params);

        return md5(urldecode(http_build_query($params) . '&secret_key=' . $secretKey));
    }

    public static function verify(array $params, string $secretKey, string $providedSign): bool
    {
        return strcasecmp(self::calculate($params, $secretKey), $providedSign) === 0;
    }
}

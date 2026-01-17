<?php

namespace App\Utils;

class AmountDisplayTransformer
{
    public static function transform($amount='0.00', $thousands_separator=',')
    {
        $precise = 2;
        if (env('APP_REGION') == 'vn') {
            $precise = 0;
        }

        return number_format(
            $amount,
            $precise,
            '.',
            $thousands_separator
        );
    }
}

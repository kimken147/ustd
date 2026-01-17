<?php


namespace App\Utils;


class FloatUtil
{

    public function numberHasFloat($number): bool
    {
        return is_numeric($number) && (fmod($number, 1) !== 0.0);
    }
}

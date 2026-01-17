<?php


namespace App\Utils;


trait WithBcMathUtil
{

    /**
     * @return BCMathUtil
     */
    public function bcMath()
    {
        return app(BCMathUtil::class);
    }

}

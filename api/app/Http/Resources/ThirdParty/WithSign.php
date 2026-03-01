<?php


namespace App\Http\Resources\ThirdParty;


use App\Models\User;
use App\Utils\SignatureCalculator;
use RuntimeException;
use Throwable;

trait WithSign
{

    /**
     * @param  User $user
     * @param  array  $data
     * @return array
     * @throws Throwable
     */
    private function withSign($user, array $data)
    {
        throw_if(
            empty($user->secret_key),
            new RuntimeException()
        );

        return array_merge($data, [
            'sign' => SignatureCalculator::calculate($data, $user->secret_key),
        ]);
    }
}

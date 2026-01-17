<?php


namespace App\Http\Resources\ThirdParty;


use App\Models\User;
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

        ksort($data);

        return array_merge($data, [
            'sign' => md5(urldecode(http_build_query($data).'&secret_key='.$user->secret_key)),
        ]);
    }
}

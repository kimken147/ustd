<?php


namespace App\Utils;


use App\Model\User;
use Closure;
use Illuminate\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class AtomicLockUtil
{

    public function keyForUserDeposit(User $user)
    {
        return "deposit_of_{$user->getKey()}";
    }

    /**
     * @param  string  $key
     * @param  Closure  $callback
     * @param  int  $lockSeconds
     * @param  int  $wait
     * @return mixed
     */
    public function lock(string $key, Closure $callback, int $lockSeconds = 100, int $wait = 1)
    {
        /** @var Lock $lock */
        $lock = Cache::lock($key, $lockSeconds);

        try {
            $lock->block($wait);

            return $callback();
        } catch (LockTimeoutException $e) {
            abort(Response::HTTP_BAD_REQUEST, __('common.Conflict! Please try again later'));
        } finally {
            optional($lock)->release();
        }

        abort(Response::HTTP_BAD_REQUEST, __('common.Conflict! Please try again later'));
    }
}

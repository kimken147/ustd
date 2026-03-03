<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SettleDelayedProviderCancelOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionStatusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SettleDelayedProviderCancelOrderTest extends TestCase
{
    use DatabaseTransactions;

    private function createUser(array $overrides = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name' => 'test_user',
            'username' => 'test_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => User::ROLE_MERCHANT,
            'status' => User::STATUS_ENABLE,
            'account_mode' => User::ACCOUNT_MODE_GENERAL,
            'secret_key' => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'balance_limit' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return User::find($id);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        $user = $this->createUser();
        $userId = $user->getKey();

        $id = DB::table('transactions')->insertGetId(array_merge([
            'from_id' => $userId,
            'to_id' => $userId,
            'locked_by_id' => $userId,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING_TIMED_OUT,
            'notify_status' => 0,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'channel_code' => 'BANK',
            'order_number' => 'ORD_' . uniqid(),
            'system_order_number' => 'SYS_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return Transaction::find($id);
    }

    public function test_handle_calls_markAsRefunded(): void
    {
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_PAYING_TIMED_OUT,
        ]);

        $statusService = Mockery::mock(TransactionStatusService::class);
        $statusService->shouldReceive('markAsRefunded')
            ->once()
            ->withArgs(function (
                Transaction $t,
                $operator,
                $note,
                bool $shouldLock
            ) use ($transaction) {
                return $t->getKey() === $transaction->getKey()
                    && $operator instanceof User
                    && $shouldLock === true; // STATUS_PAYING_TIMED_OUT === STATUS_PAYING_TIMED_OUT => true
            });

        $job = new SettleDelayedProviderCancelOrder($transaction);
        $job->handle($statusService);
    }

    public function test_handle_passes_shouldLock_false_for_non_paying_timed_out_status(): void
    {
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_PAYING,
        ]);

        $statusService = Mockery::mock(TransactionStatusService::class);
        $statusService->shouldReceive('markAsRefunded')
            ->once()
            ->withArgs(function (
                Transaction $t,
                $operator,
                $note,
                bool $shouldLock
            ) use ($transaction) {
                return $t->getKey() === $transaction->getKey()
                    && $operator instanceof User
                    && $shouldLock === false; // STATUS_PAYING !== STATUS_PAYING_TIMED_OUT => false
            });

        $job = new SettleDelayedProviderCancelOrder($transaction);
        $job->handle($statusService);
    }
}

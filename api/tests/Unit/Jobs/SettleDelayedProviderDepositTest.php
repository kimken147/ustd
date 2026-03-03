<?php

namespace Tests\Unit\Jobs;

use App\Jobs\SettleDelayedProviderDeposit;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionStatusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SettleDelayedProviderDepositTest extends TestCase
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
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
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

    public function test_handle_calls_settleToWallet(): void
    {
        $transaction = $this->createTransaction();

        $statusService = Mockery::mock(TransactionStatusService::class);
        $statusService->shouldReceive('settleToWallet')
            ->once()
            ->withArgs(function (Transaction $t) use ($transaction) {
                return $t->getKey() === $transaction->getKey();
            });

        $job = new SettleDelayedProviderDeposit($transaction);
        $job->handle($statusService);
    }
}

<?php

namespace Tests\Unit\Repository;

use App\Models\Transaction;
use App\Models\User;
use App\Repository\UserTransactionStatRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserTransactionStatRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private UserTransactionStatRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new UserTransactionStatRepository();
    }

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

    private function createTransaction(array $overrides = []): int
    {
        return DB::table('transactions')->insertGetId(array_merge([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'notify_status' => 0,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'channel_code' => 'BANK',
            'order_number' => 'ORD_' . uniqid(),
            'system_order_number' => 'SYS_' . uniqid(),
            'confirmed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createWallet(int $userId, array $overrides = []): int
    {
        return DB::table('wallets')->insertGetId(array_merge([
            'user_id' => $userId,
            'status' => 1,
            'balance' => '500.00',
            'frozen_balance' => '0.00',
            'withdraw_fee' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function test_getSelfTransactionStats_returns_grouped_stats(): void
    {
        $user = $this->createUser();
        $userId = $user->getKey();
        $today = Carbon::today()->toDateString();

        $this->createTransaction([
            'from_id' => $userId,
            'status' => Transaction::STATUS_SUCCESS,
            'amount' => '200.00',
            'confirmed_at' => now(),
        ]);
        $this->createTransaction([
            'from_id' => $userId,
            'status' => Transaction::STATUS_MANUAL_SUCCESS,
            'amount' => '300.00',
            'confirmed_at' => now(),
        ]);

        $result = $this->repository->getSelfTransactionStats([$userId], 'from_id', 'amount');

        $key = "{$userId}_{$today}";
        $this->assertArrayHasKey($key, $result->toArray());
        $this->assertEquals('500.00', $result[$key]);
    }

    public function test_getDescendantTransactionStats_returns_grouped_by_date(): void
    {
        $user = $this->createUser();
        $userId = $user->getKey();
        $today = Carbon::today()->toDateString();

        $this->createTransaction([
            'from_id' => $userId,
            'status' => Transaction::STATUS_SUCCESS,
            'amount' => '150.00',
            'confirmed_at' => now(),
        ]);
        $this->createTransaction([
            'from_id' => $userId,
            'status' => Transaction::STATUS_SUCCESS,
            'amount' => '250.00',
            'confirmed_at' => now(),
        ]);

        $result = $this->repository->getDescendantTransactionStats([$userId], 'from_id', 'amount');

        $this->assertArrayHasKey($today, $result->toArray());
        $this->assertEquals('400.00', $result[$today]);
    }

    public function test_getWalletBalanceTotal_returns_sum(): void
    {
        $user1 = $this->createUser();
        $user2 = $this->createUser();

        $this->createWallet($user1->getKey(), ['balance' => '1000.00']);
        $this->createWallet($user2->getKey(), ['balance' => '500.00']);

        $result = $this->repository->getWalletBalanceTotal([
            $user1->getKey(),
            $user2->getKey(),
        ]);

        $this->assertEquals('1500.00', $result);
    }

    public function test_getWalletBalanceTotal_returns_zero_when_no_wallets(): void
    {
        // Use user IDs that don't have wallets
        $result = $this->repository->getWalletBalanceTotal([999998, 999999]);

        $this->assertEquals('0', $result);
    }
}

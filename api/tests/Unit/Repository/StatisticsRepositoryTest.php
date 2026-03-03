<?php

namespace Tests\Unit\Repository;

use App\Models\Transaction;
use App\Models\User;
use App\Repository\StatisticsRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatisticsRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private StatisticsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new StatisticsRepository();

        // Ensure the FORCE INDEX hints can resolve.
        // These indexes may not exist in the test DB if migrations were not fully applied.
        $this->ensureIndex('transactions', 'transactions_confirmed_at_to_id_index', ['confirmed_at', 'to_id']);
        $this->ensureIndex('transactions', 'transactions_confirmed_at_from_id_index', ['confirmed_at', 'from_id']);
    }

    /**
     * Create an index if it does not already exist.
     */
    private function ensureIndex(string $table, string $indexName, array $columns): void
    {
        $exists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        if (empty($exists)) {
            $cols = implode(', ', $columns);
            DB::statement("CREATE INDEX {$indexName} ON {$table} ({$cols})");
        }
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

    private function createTransactionFee(int $transactionId, int $userId, array $overrides = []): void
    {
        DB::table('transaction_fees')->insert(array_merge([
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'profit' => '10.00',
            'actual_profit' => '8.00',
            'fee' => '2.00',
            'actual_fee' => '2.00',
        ], $overrides));
    }

    public function test_getTransactionStatsByUsername_returns_grouped_stats(): void
    {
        $merchant = $this->createUser(['role' => User::ROLE_MERCHANT]);
        $start = now()->subDay()->startOfDay()->toDateTimeString();
        $end = now()->endOfDay()->toDateTimeString();

        $this->createTransaction([
            'to_id' => $merchant->getKey(),
            'status' => Transaction::STATUS_SUCCESS,
            'amount' => '200.00',
            'confirmed_at' => now(),
        ]);
        $this->createTransaction([
            'to_id' => $merchant->getKey(),
            'status' => Transaction::STATUS_SUCCESS,
            'amount' => '300.00',
            'confirmed_at' => now(),
        ]);

        $result = $this->repository->getTransactionStatsByUsername($start, $end);

        $this->assertArrayHasKey($merchant->username, $result->toArray());
        $row = $result[$merchant->username];
        $this->assertEquals(2, $row->count);
        $this->assertEquals('500.00', $row->sum);
    }

    public function test_getTransactionStatsByDate_returns_grouped_by_date(): void
    {
        $merchant = $this->createUser(['role' => User::ROLE_MERCHANT]);
        $start = now()->subDay()->startOfDay()->toDateTimeString();
        $end = now()->endOfDay()->toDateTimeString();
        $today = now()->toDateString();

        $this->createTransaction([
            'to_id' => $merchant->getKey(),
            'status' => Transaction::STATUS_SUCCESS,
            'amount' => '150.00',
            'confirmed_at' => now(),
        ]);
        $this->createTransaction([
            'to_id' => $merchant->getKey(),
            'status' => Transaction::STATUS_MANUAL_SUCCESS,
            'amount' => '250.00',
            'confirmed_at' => now(),
        ]);

        $result = $this->repository->getTransactionStatsByDate($start, $end);

        $this->assertArrayHasKey($today, $result->toArray());
        $row = $result[$today];
        // Use >= because existing data in the DB may contribute to the group
        $this->assertGreaterThanOrEqual(2, $row->count);
        $this->assertGreaterThanOrEqual(400.00, (float) $row->sum);
    }

    public function test_getWithdrawStatsByUsername_returns_grouped_stats(): void
    {
        $merchant = $this->createUser(['role' => User::ROLE_MERCHANT]);
        $start = now()->subDay()->startOfDay()->toDateTimeString();
        $end = now()->endOfDay()->toDateTimeString();

        $this->createTransaction([
            'from_id' => $merchant->getKey(),
            'sub_type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_SUCCESS,
            'amount' => '500.00',
            'confirmed_at' => now(),
        ]);
        $this->createTransaction([
            'from_id' => $merchant->getKey(),
            'sub_type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_SUCCESS,
            'amount' => '300.00',
            'confirmed_at' => now(),
        ]);

        $result = $this->repository->getWithdrawStatsByUsername($start, $end);

        $this->assertArrayHasKey($merchant->username, $result->toArray());
        $row = $result[$merchant->username];
        $this->assertEquals(2, $row->count);
        $this->assertEquals('800.00', $row->sum);
    }

    public function test_getSystemProfit_returns_sum_of_system_fees(): void
    {
        $start = now()->subDay()->startOfDay()->toDateTimeString();
        $end = now()->endOfDay()->toDateTimeString();

        $merchant = $this->createUser(['role' => User::ROLE_MERCHANT]);

        $txId1 = $this->createTransaction([
            'to_id' => $merchant->getKey(),
            'status' => Transaction::STATUS_SUCCESS,
            'confirmed_at' => now(),
        ]);
        $txId2 = $this->createTransaction([
            'to_id' => $merchant->getKey(),
            'status' => Transaction::STATUS_SUCCESS,
            'confirmed_at' => now(),
        ]);

        // System fees (user_id = 0)
        $this->createTransactionFee($txId1, 0, ['actual_profit' => '5.00']);
        $this->createTransactionFee($txId2, 0, ['actual_profit' => '3.00']);

        // Non-system fee (should be excluded)
        $this->createTransactionFee($txId1, $merchant->getKey(), ['actual_profit' => '100.00']);

        $result = $this->repository->getSystemProfit($start, $end);

        $this->assertEquals('8.00', $result->sum);
    }

    public function test_getTransactionStatsByUsername_returns_empty_when_no_data(): void
    {
        $start = '2099-01-01 00:00:00';
        $end = '2099-12-31 23:59:59';

        $result = $this->repository->getTransactionStatsByUsername($start, $end);

        $this->assertTrue($result->isEmpty());
    }
}

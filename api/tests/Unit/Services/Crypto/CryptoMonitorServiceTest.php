<?php

namespace Tests\Unit\Services\Crypto;

use App\Models\Transaction;
use App\Models\UsdtDepositMonitor;
use App\Services\Crypto\Adapters\Trc20Adapter;
use App\Services\Crypto\CryptoMonitorService;
use App\Services\Crypto\DTO\ChainTransaction;
use App\Services\Transaction\TransactionStatusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class CryptoMonitorServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CryptoMonitorService $service;
    private $mockAdapter;
    private $mockStatusService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAdapter = Mockery::mock(Trc20Adapter::class);
        $this->app->instance(Trc20Adapter::class, $this->mockAdapter);

        $this->mockStatusService = Mockery::mock(TransactionStatusService::class);
        $this->service = new CryptoMonitorService($this->mockStatusService);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        $defaults = [
            'type' => Transaction::TYPE_NORMAL_DEPOSIT,
            'status' => Transaction::STATUS_PAYING,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
            'from_channel_account' => json_encode([]),
            'to_channel_account' => json_encode([]),
            'amount' => '100.500000',
            'floating_amount' => '100.500000',
            'actual_amount' => '0',
        ];

        $data = array_merge($defaults, $overrides);
        $id = DB::table('transactions')->insertGetId($data);

        return Transaction::find($id);
    }

    private function createMonitor(int $transactionId, array $overrides = []): UsdtDepositMonitor
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'TestUser',
            'username' => 'TESTUSER' . mt_rand(10000, 99999),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'role' => 3,
            'status' => 1,
            'account_mode' => 1,
            'secret_key' => bin2hex(random_bytes(16)),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
        ]);

        $accountId = DB::table('user_channel_accounts')->insertGetId([
            'user_id' => $userId,
            'status' => 1,
            'account' => 'TMonitorAddress' . mt_rand(100, 999),
            'detail' => json_encode([]),
        ]);

        $defaults = [
            'transaction_id' => $transactionId,
            'user_channel_account_id' => $accountId,
            'address' => 'TMonitorAddress123',
            'chain_network' => 'trc20',
            'expected_amount' => '100.500000',
            'received_amount' => 0,
            'tx_hash' => null,
            'status' => UsdtDepositMonitor::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
            'matched_at' => null,
            'confirmed_at' => null,
            'last_polled_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $data = array_merge($defaults, $overrides);
        $id = DB::table('usdt_deposit_monitors')->insertGetId($data);

        return UsdtDepositMonitor::find($id);
    }

    // ---------------------------------------------------------------
    // Tests
    // ---------------------------------------------------------------

    public function test_poll_no_pending_monitors(): void
    {
        // No monitors exist — adapter should not be called
        $this->mockAdapter->shouldNotReceive('fetchIncomingTransactions');

        $this->service->poll();
    }

    public function test_poll_happy_path_match(): void
    {
        $transaction = $this->createTransaction(['amount' => '100.500000']);
        $monitor = $this->createMonitor($transaction->id, ['expected_amount' => '100.500000']);

        $chainTx = new ChainTransaction(
            txHash: 'matched_tx_hash_123',
            from: 'TSender',
            to: 'TMonitorAddress123',
            amount: '100.500000',
            timestamp: 1700000000000,
            confirmations: 1,
        );

        $this->mockAdapter->shouldReceive('fetchIncomingTransactions')
            ->once()
            ->andReturn(collect([$chainTx]));

        $this->mockStatusService->shouldReceive('markAsSuccess')
            ->once();

        $this->service->poll();

        $monitor->refresh();
        $this->assertEquals(UsdtDepositMonitor::STATUS_CONFIRMED, $monitor->status);
        $this->assertEquals('matched_tx_hash_123', $monitor->tx_hash);
        $this->assertEquals('100.500000', $monitor->received_amount);
        $this->assertNotNull($monitor->matched_at);
        $this->assertNotNull($monitor->confirmed_at);
    }

    public function test_poll_amount_mismatch(): void
    {
        $transaction = $this->createTransaction();
        $monitor = $this->createMonitor($transaction->id, ['expected_amount' => '100.500000']);

        $chainTx = new ChainTransaction(
            txHash: 'wrong_amount_tx',
            from: 'TSender',
            to: 'TMonitorAddress123',
            amount: '99.000000', // Different amount
            timestamp: 1700000000000,
            confirmations: 1,
        );

        $this->mockAdapter->shouldReceive('fetchIncomingTransactions')
            ->once()
            ->andReturn(collect([$chainTx]));

        $this->mockStatusService->shouldNotReceive('markAsSuccess');

        $this->service->poll();

        $monitor->refresh();
        $this->assertEquals(UsdtDepositMonitor::STATUS_PENDING, $monitor->status);
        $this->assertNull($monitor->tx_hash);
    }

    public function test_poll_duplicate_tx_hash_prevention(): void
    {
        // Create first monitor that already matched this tx_hash
        $transaction1 = $this->createTransaction();
        $monitor1 = $this->createMonitor($transaction1->id, [
            'expected_amount' => '100.500000',
            'tx_hash' => 'already_used_tx_hash',
            'status' => UsdtDepositMonitor::STATUS_CONFIRMED,
        ]);

        // Create second monitor with same expected amount
        $transaction2 = $this->createTransaction();
        $monitor2 = $this->createMonitor($transaction2->id, [
            'expected_amount' => '100.500000',
        ]);

        $chainTx = new ChainTransaction(
            txHash: 'already_used_tx_hash', // Same tx_hash
            from: 'TSender',
            to: 'TMonitorAddress123',
            amount: '100.500000',
            timestamp: 1700000000000,
            confirmations: 1,
        );

        $this->mockAdapter->shouldReceive('fetchIncomingTransactions')
            ->andReturn(collect([$chainTx]));

        $this->mockStatusService->shouldNotReceive('markAsSuccess');

        $this->service->poll();

        $monitor2->refresh();
        $this->assertEquals(UsdtDepositMonitor::STATUS_PENDING, $monitor2->status);
        $this->assertNull($monitor2->tx_hash);
    }

    public function test_poll_updates_last_polled_at(): void
    {
        $transaction = $this->createTransaction();
        $monitor = $this->createMonitor($transaction->id);

        $this->mockAdapter->shouldReceive('fetchIncomingTransactions')
            ->once()
            ->andReturn(collect([]));

        $this->service->poll();

        $monitor->refresh();
        $this->assertNotNull($monitor->last_polled_at);
    }

    public function test_expire_timed_out_monitors(): void
    {
        // Expired monitor
        $txExpired = $this->createTransaction();
        $expiredMonitor = $this->createMonitor($txExpired->id, [
            'expires_at' => now()->subMinutes(5), // Already expired
            'status' => UsdtDepositMonitor::STATUS_PENDING,
        ]);

        // A pending (non-expired) monitor is needed so poll() doesn't return early
        $txPending = $this->createTransaction();
        $pendingMonitor = $this->createMonitor($txPending->id, [
            'expires_at' => now()->addMinutes(30),
            'address' => 'TDifferentAddress456',
        ]);

        $this->mockAdapter->shouldReceive('fetchIncomingTransactions')
            ->andReturn(collect([]));

        $this->service->poll();

        $expiredMonitor->refresh();
        $this->assertEquals(UsdtDepositMonitor::STATUS_EXPIRED, $expiredMonitor->status);
    }

    public function test_confirms_deposit_via_transaction_status_service(): void
    {
        $transaction = $this->createTransaction(['status' => Transaction::STATUS_PAYING]);
        $monitor = $this->createMonitor($transaction->id, ['expected_amount' => '50.000000']);

        $chainTx = new ChainTransaction(
            txHash: 'confirm_tx_hash',
            from: 'TSender',
            to: 'TMonitorAddress123',
            amount: '50.000000',
            timestamp: 1700000000000,
            confirmations: 1,
        );

        $this->mockAdapter->shouldReceive('fetchIncomingTransactions')
            ->once()
            ->andReturn(collect([$chainTx]));

        $this->mockStatusService->shouldReceive('markAsSuccess')
            ->once()
            ->with(Mockery::on(function ($tx) use ($transaction) {
                return $tx->id === $transaction->id;
            }));

        $this->service->poll();
    }

    public function test_only_matches_status_paying_transactions(): void
    {
        // Transaction in SUCCESS status — should not be matched
        $transaction = $this->createTransaction(['status' => Transaction::STATUS_SUCCESS]);
        $monitor = $this->createMonitor($transaction->id, ['expected_amount' => '100.500000']);

        $chainTx = new ChainTransaction(
            txHash: 'should_not_match_tx',
            from: 'TSender',
            to: 'TMonitorAddress123',
            amount: '100.500000',
            timestamp: 1700000000000,
            confirmations: 1,
        );

        $this->mockAdapter->shouldReceive('fetchIncomingTransactions')
            ->andReturn(collect([$chainTx]));

        $this->mockStatusService->shouldNotReceive('markAsSuccess');

        $this->service->poll();
    }
}

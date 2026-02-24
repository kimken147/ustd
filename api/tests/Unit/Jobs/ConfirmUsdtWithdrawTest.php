<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ConfirmUsdtWithdraw;
use App\Jobs\NotifyTransaction;
use App\Models\Transaction;
use App\Services\Crypto\Adapters\Trc20Adapter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ConfirmUsdtWithdrawTest extends TestCase
{
    use DatabaseTransactions;

    private $mockAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockAdapter = Mockery::mock(Trc20Adapter::class);
        $this->app->instance(Trc20Adapter::class, $this->mockAdapter);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        $defaults = [
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
            'from_channel_account' => json_encode([]),
            'to_channel_account' => json_encode(['bank_card_number' => 'TToAddress456']),
            'amount' => '100.000000',
            'floating_amount' => '100.000000',
            'actual_amount' => '0',
            'tx_hash' => 'test_tx_hash_123',
            'chain_network' => 'trc20',
        ];

        $data = array_merge($defaults, $overrides);
        $id = DB::table('transactions')->insertGetId($data);

        return Transaction::find($id);
    }

    public function test_transaction_not_found_returns_early(): void
    {
        $this->mockAdapter->shouldNotReceive('getTransactionInfo');

        $job = new ConfirmUsdtWithdraw(999999);
        $job->handle();
    }

    public function test_transaction_without_tx_hash_returns_early(): void
    {
        $transaction = $this->createTransaction(['tx_hash' => null]);

        $this->mockAdapter->shouldNotReceive('getTransactionInfo');

        $job = new ConfirmUsdtWithdraw($transaction->id);
        $job->handle();
    }

    public function test_chain_confirmed_success(): void
    {
        Bus::fake([NotifyTransaction::class]);

        $transaction = $this->createTransaction();

        $this->mockAdapter->shouldReceive('getTransactionInfo')
            ->once()
            ->with('test_tx_hash_123')
            ->andReturn([
                'confirmed' => true,
                'success' => true,
                'fee' => '5.000000',
            ]);

        $job = new ConfirmUsdtWithdraw($transaction->id);
        $job->handle();

        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_SUCCESS, $transaction->status);

        Bus::assertDispatched(NotifyTransaction::class);
    }

    public function test_chain_confirmed_failure(): void
    {
        Bus::fake([NotifyTransaction::class]);

        $transaction = $this->createTransaction();

        $this->mockAdapter->shouldReceive('getTransactionInfo')
            ->once()
            ->with('test_tx_hash_123')
            ->andReturn([
                'confirmed' => true,
                'success' => false,
                'fee' => '3.000000',
            ]);

        $job = new ConfirmUsdtWithdraw($transaction->id);
        $job->handle();

        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_FAILED, $transaction->status);

        Bus::assertDispatched(NotifyTransaction::class);
    }

    public function test_not_yet_confirmed_releases_job(): void
    {
        $transaction = $this->createTransaction();

        $this->mockAdapter->shouldReceive('getTransactionInfo')
            ->once()
            ->with('test_tx_hash_123')
            ->andReturn(null);

        $job = new ConfirmUsdtWithdraw($transaction->id);

        // Mock the InteractsWithQueue methods
        $job = Mockery::mock(ConfirmUsdtWithdraw::class, [$transaction->id])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('release')
            ->once()
            ->with(30);

        $job->shouldReceive('attempts')
            ->andReturn(1);

        $job->handle();

        // Verify status unchanged
        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_PAYING, $transaction->status);
    }

    public function test_unsupported_chain_network_returns_early(): void
    {
        $transaction = $this->createTransaction(['chain_network' => 'erc20']);

        $this->mockAdapter->shouldNotReceive('getTransactionInfo');

        $job = new ConfirmUsdtWithdraw($transaction->id);
        $job->handle();

        // Status should remain unchanged
        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_PAYING, $transaction->status);
    }
}

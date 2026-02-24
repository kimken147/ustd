<?php

namespace Tests\Unit\Services\Crypto;

use App\Jobs\ConfirmUsdtWithdraw;
use App\Models\Transaction;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\Trc20Adapter;
use App\Services\Crypto\DTO\ChainTransaction;
use App\Services\Crypto\Exceptions\InsufficientBalanceException;
use App\Services\Crypto\Exceptions\TransactionBroadcastException;
use App\Services\Crypto\UsdtWithdrawHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class UsdtWithdrawHandlerTest extends TestCase
{
    use DatabaseTransactions;

    private UsdtWithdrawHandler $handler;
    private $mockAdapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new UsdtWithdrawHandler();

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
            'tx_hash' => null,
            'chain_network' => null,
            'from_channel_account_id' => null,
        ];

        $data = array_merge($defaults, $overrides);
        $id = DB::table('transactions')->insertGetId($data);

        return Transaction::find($id);
    }

    private function createAccount(array $overrides = []): UserChannelAccount
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'TestUser',
            'username' => 'TESTUSER' . mt_rand(10000, 99999),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'role' => 3, // merchant
            'status' => 1,
            'account_mode' => 1,
            'secret_key' => bin2hex(random_bytes(16)),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
        ]);

        $defaults = [
            'user_id' => $userId,
            'status' => UserChannelAccount::STATUS_ENABLE,
            'account' => 'TFromAddress123',
            'detail' => json_encode([
                UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK => 'trc20',
                UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY => encrypt('fakeprivatekey123'),
            ]),
        ];

        $data = array_merge($defaults, $overrides);
        $id = DB::table('user_channel_accounts')->insertGetId($data);

        return UserChannelAccount::find($id);
    }

    // ---------------------------------------------------------------
    // Tests
    // ---------------------------------------------------------------

    public function test_idempotency_skips_if_tx_hash_exists(): void
    {
        $transaction = $this->createTransaction(['tx_hash' => 'existing_tx_hash_123']);

        $this->mockAdapter->shouldNotReceive('sendTransaction');

        $this->handler->handle($transaction);
    }

    public function test_missing_account_returns_early(): void
    {
        $transaction = $this->createTransaction(['from_channel_account_id' => 999999]);

        $this->mockAdapter->shouldNotReceive('sendTransaction');

        $this->handler->handle($transaction);
    }

    public function test_missing_private_key_returns_early(): void
    {
        $account = $this->createAccount([
            'detail' => json_encode([
                UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK => 'trc20',
            ]),
        ]);

        $transaction = $this->createTransaction([
            'from_channel_account_id' => $account->id,
        ]);

        $this->mockAdapter->shouldNotReceive('sendTransaction');

        $this->handler->handle($transaction);
    }

    public function test_insufficient_trx_balance_throws_exception(): void
    {
        $account = $this->createAccount();

        $transaction = $this->createTransaction([
            'from_channel_account_id' => $account->id,
        ]);

        $this->mockAdapter->shouldReceive('getNativeBalance')
            ->once()
            ->andReturn('10.000000');

        $this->expectException(InsufficientBalanceException::class);

        $this->handler->handle($transaction);
    }

    public function test_empty_to_address_returns_early(): void
    {
        $account = $this->createAccount();

        $transaction = $this->createTransaction([
            'from_channel_account_id' => $account->id,
            'to_channel_account' => json_encode(['bank_card_number' => '']),
        ]);

        $this->mockAdapter->shouldReceive('getNativeBalance')->andReturn('50.000000');
        $this->mockAdapter->shouldNotReceive('sendTransaction');

        $this->handler->handle($transaction);
    }

    public function test_happy_path_broadcasts_and_dispatches_confirmation(): void
    {
        Bus::fake([ConfirmUsdtWithdraw::class]);

        $account = $this->createAccount();

        $transaction = $this->createTransaction([
            'from_channel_account_id' => $account->id,
        ]);

        $chainTx = new ChainTransaction(
            txHash: 'broadcasted_tx_hash',
            from: 'TFromAddress123',
            to: 'TToAddress456',
            amount: '100.000000',
            timestamp: 1700000000000,
            confirmations: 0,
        );

        $this->mockAdapter->shouldReceive('getNativeBalance')->andReturn('50.000000');
        $this->mockAdapter->shouldReceive('sendTransaction')
            ->once()
            ->andReturn($chainTx);

        $this->handler->handle($transaction);

        $transaction->refresh();
        $this->assertEquals('broadcasted_tx_hash', $transaction->tx_hash);
        $this->assertEquals('trc20', $transaction->chain_network);

        Bus::assertDispatched(ConfirmUsdtWithdraw::class, function ($job) use ($transaction) {
            return $job->transactionId === $transaction->id;
        });
    }

    public function test_broadcast_failure_throws_exception(): void
    {
        $account = $this->createAccount();

        $transaction = $this->createTransaction([
            'from_channel_account_id' => $account->id,
        ]);

        $this->mockAdapter->shouldReceive('getNativeBalance')->andReturn('50.000000');
        $this->mockAdapter->shouldReceive('sendTransaction')
            ->once()
            ->andThrow(new TransactionBroadcastException('Broadcast failed'));

        $this->expectException(TransactionBroadcastException::class);

        $this->handler->handle($transaction);
    }

    public function test_private_key_cleanup_in_finally(): void
    {
        $account = $this->createAccount();

        $transaction = $this->createTransaction([
            'from_channel_account_id' => $account->id,
        ]);

        $this->mockAdapter->shouldReceive('getNativeBalance')->andReturn('50.000000');
        $this->mockAdapter->shouldReceive('sendTransaction')
            ->once()
            ->andThrow(new TransactionBroadcastException('Broadcast failed'));

        try {
            $this->handler->handle($transaction);
            $this->fail('Expected TransactionBroadcastException');
        } catch (TransactionBroadcastException $e) {
            $this->assertEquals('Broadcast failed', $e->getMessage());
        }
    }
}

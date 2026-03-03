<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessUsdtWithdraw;
use App\Models\Transaction;
use App\Services\Crypto\UsdtWithdrawHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ProcessUsdtWithdrawTest extends TestCase
{
    use DatabaseTransactions;

    private $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = Mockery::mock(UsdtWithdrawHandler::class);
        $this->app->instance(UsdtWithdrawHandler::class, $this->mockHandler);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        $defaults = [
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'channel_code' => 'USDT',
            'order_number' => 'ORD_' . uniqid(),
            'system_order_number' => 'SYS_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $data = array_merge($defaults, $overrides);
        $id = DB::table('transactions')->insertGetId($data);

        return Transaction::find($id);
    }

    public function test_handle_calls_handler_when_transaction_exists(): void
    {
        $transaction = $this->createTransaction();

        $this->mockHandler->shouldReceive('handle')
            ->once()
            ->withArgs(function (Transaction $t) use ($transaction) {
                return $t->getKey() === $transaction->getKey();
            });

        $job = new ProcessUsdtWithdraw($transaction->getKey());
        $job->handle($this->mockHandler);
    }

    public function test_handle_returns_early_when_transaction_not_found(): void
    {
        $this->mockHandler->shouldNotReceive('handle');

        $job = new ProcessUsdtWithdraw(999999);
        $job->handle($this->mockHandler);
    }
}

<?php

namespace Tests\Unit\Services\Transaction;

use App\Models\Transaction;
use App\Services\Transaction\TransactionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionService();
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function createTransaction(array $overrides = []): Transaction
    {
        $id = DB::table('transactions')->insertGetId(array_merge([
            'type'                 => Transaction::TYPE_NORMAL_DEPOSIT,
            'status'               => Transaction::STATUS_PENDING_REVIEW,
            'notify_status'        => Transaction::NOTIFY_STATUS_NONE,
            'amount'               => '100.00',
            'floating_amount'      => '0.00',
            'actual_amount'        => '100.00',
            'from_channel_account' => json_encode([]),
            'to_channel_account'   => json_encode([]),
            'system_order_number'  => 'SYS' . uniqid(),
            'created_at'           => now(),
            'updated_at'           => now(),
        ], $overrides));
        return Transaction::find($id);
    }

    // ---------------------------------------------------------------
    //  findOne
    // ---------------------------------------------------------------

    public function test_findOne_returns_transaction(): void
    {
        $transaction = $this->createTransaction();

        $result = $this->service->findOne((string) $transaction->id);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals($transaction->id, $result->id);
    }

    public function test_findOne_returns_null_when_not_found(): void
    {
        $result = $this->service->findOne('999999999');

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    //  findOneByOrderId
    // ---------------------------------------------------------------

    public function test_findOneByOrderId_returns_transaction(): void
    {
        $transaction = $this->createTransaction();

        $result = $this->service->findOneByOrderId((string) $transaction->id);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals($transaction->id, $result->id);
    }

    public function test_findOneByOrderId_returns_null_when_not_found(): void
    {
        $result = $this->service->findOneByOrderId('999999999');

        $this->assertNull($result);
    }
}

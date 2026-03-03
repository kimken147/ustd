<?php

namespace Tests\Unit\Services;

use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\User;
use App\Services\TransactionNoteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionNoteServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TransactionNoteService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionNoteService(new TransactionNote());
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function createUser(array $overrides = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name'             => 'test_user',
            'username'         => 'test_' . uniqid(),
            'password'         => bcrypt('password'),
            'role'             => User::ROLE_MERCHANT,
            'status'           => User::STATUS_ENABLE,
            'account_mode'     => User::ACCOUNT_MODE_GENERAL,
            'secret_key'       => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'balance_limit'    => 0,
            'created_at'       => now(),
            'updated_at'       => now(),
        ], $overrides));
        return User::find($id);
    }

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
    //  Tests
    // ---------------------------------------------------------------

    public function test_create_creates_transaction_note(): void
    {
        $transaction = $this->createTransaction();

        $note = $this->service->create($transaction->id, 'Test note content');

        $this->assertInstanceOf(TransactionNote::class, $note);
        $this->assertEquals($transaction->id, $note->transaction_id);
        $this->assertEquals('Test note content', $note->note);
        $this->assertEquals(0, $note->user_id);
        $this->assertDatabaseHas('transaction_notes', [
            'transaction_id' => $transaction->id,
            'note'           => 'Test note content',
            'user_id'        => 0,
        ]);
    }

    public function test_create_with_user_id(): void
    {
        $transaction = $this->createTransaction();
        $user = $this->createUser();

        $note = $this->service->create($transaction->id, 'Admin note', $user->id);

        $this->assertInstanceOf(TransactionNote::class, $note);
        $this->assertEquals($transaction->id, $note->transaction_id);
        $this->assertEquals('Admin note', $note->note);
        $this->assertEquals($user->id, $note->user_id);
        $this->assertDatabaseHas('transaction_notes', [
            'transaction_id' => $transaction->id,
            'note'           => 'Admin note',
            'user_id'        => $user->id,
        ]);
    }
}

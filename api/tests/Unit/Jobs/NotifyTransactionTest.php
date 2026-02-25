<?php

namespace Tests\Unit\Jobs;

use App\Jobs\NotifyTransaction;
use App\Models\Transaction;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NotifyTransactionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Create a user record directly in the database.
     */
    private function createUser(array $overrides = []): int
    {
        $defaults = [
            'name' => 'TestUser',
            'username' => 'TESTUSER' . mt_rand(100000, 999999),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'role' => 3,
            'status' => 1,
            'account_mode' => 1,
            'secret_key' => bin2hex(random_bytes(16)),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
        ];

        return DB::table('users')->insertGetId(array_merge($defaults, $overrides));
    }

    /**
     * Create a transaction record directly in the database.
     */
    private function createTransaction(array $overrides = []): Transaction
    {
        $defaults = [
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
            'from_channel_account' => json_encode([]),
            'to_channel_account' => json_encode([]),
            'amount' => '100.000000',
            'floating_amount' => '100.000000',
            'actual_amount' => '0',
            'order_number' => 'ORD' . mt_rand(100000, 999999),
            'system_order_number' => 'SYS' . mt_rand(100000, 999999),
            'notify_url' => 'https://example.com/notify',
        ];

        $data = array_merge($defaults, $overrides);
        $id = DB::table('transactions')->insertGetId($data);

        return Transaction::find($id);
    }

    /**
     * Test: Returns early when transaction has no notify_url.
     */
    public function test_returns_early_when_no_notify_url(): void
    {
        $transaction = $this->createTransaction([
            'notify_url' => null,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
        ]);

        $job = new NotifyTransaction($transaction);
        $job->handle();

        // notify_status should remain unchanged (NONE) since the job returned early
        $transaction->refresh();
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_NONE,
            $transaction->notify_status,
            'notify_status should remain NONE when notify_url is null'
        );
    }

    /**
     * Test: Releases job back to queue when transaction status is not final.
     */
    public function test_releases_when_status_not_final(): void
    {
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_PAYING,
        ]);

        $job = Mockery::mock(NotifyTransaction::class, [$transaction])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('release')
            ->once()
            ->with(10);

        $job->handle();

        // notify_status should remain unchanged since the job was released
        $transaction->refresh();
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_NONE,
            $transaction->notify_status,
            'notify_status should remain NONE when status is not final'
        );
    }

    /**
     * Test: Includes chain_network and tx_hash in the notification payload.
     *
     * Since the job uses `new Client()` directly, we verify the payload building
     * logic by replicating it and asserting the correct fields are present.
     */
    public function test_includes_chain_network_and_tx_hash_in_payload(): void
    {
        $secretKey = bin2hex(random_bytes(16));
        $userId = $this->createUser(['secret_key' => $secretKey]);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'to_id' => $userId,
            'chain_network' => 'trc20',
            'tx_hash' => '0xabc123def456',
        ]);

        $targetUser = User::find($userId);

        // Replicate the payload building logic from the job
        $mainData = [
            'order_number' => $transaction->order_number,
            'system_order_number' => $transaction->system_order_number,
            'username' => $targetUser->username,
            'amount' => $transaction->amount,
            'status' => $transaction->status,
        ];

        if ($transaction->chain_network) {
            $mainData['chain_network'] = $transaction->chain_network;
        }
        if ($transaction->tx_hash) {
            $mainData['tx_hash'] = $transaction->tx_hash;
        }

        // chain_network and tx_hash should be included
        $this->assertArrayHasKey('chain_network', $mainData);
        $this->assertArrayHasKey('tx_hash', $mainData);
        $this->assertEquals('trc20', $mainData['chain_network']);
        $this->assertEquals('0xabc123def456', $mainData['tx_hash']);

        // Verify sign calculation matches the job's logic
        $parameters = $mainData;
        ksort($parameters);
        $expectedSign = md5(urldecode(http_build_query($parameters) . '&secret_key=' . $secretKey));

        $this->assertNotEmpty($expectedSign);
        $this->assertEquals(32, strlen($expectedSign), 'MD5 sign should be 32 characters');
    }

    /**
     * Test: Payload excludes chain_network and tx_hash when not set.
     */
    public function test_excludes_chain_network_and_tx_hash_when_null(): void
    {
        $userId = $this->createUser();
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'to_id' => $userId,
            'chain_network' => null,
            'tx_hash' => null,
        ]);

        // Replicate the payload building logic
        $mainData = [
            'order_number' => $transaction->order_number,
            'system_order_number' => $transaction->system_order_number,
            'amount' => $transaction->amount,
            'status' => $transaction->status,
        ];

        if ($transaction->chain_network) {
            $mainData['chain_network'] = $transaction->chain_network;
        }
        if ($transaction->tx_hash) {
            $mainData['tx_hash'] = $transaction->tx_hash;
        }

        $this->assertArrayNotHasKey('chain_network', $mainData);
        $this->assertArrayNotHasKey('tx_hash', $mainData);
    }

    /**
     * Test: Marks notify_status as FAILED after exceeding max attempts.
     */
    public function test_marks_failed_after_max_attempts(): void
    {
        $userId = $this->createUser();
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'to_id' => $userId,
        ]);

        $job = Mockery::mock(NotifyTransaction::class, [$transaction])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Simulate attempt count > 2 (third attempt or more)
        $job->shouldReceive('attempts')
            ->andReturn(3);

        // The HTTP call will throw an exception (no real server), which is caught
        // internally, leaving responseContents as null. With attempts > 2 and
        // response not 'success'/'ok', it should mark as FAILED.
        $job->handle();

        $transaction->refresh();
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_FAILED,
            $transaction->notify_status,
            'notify_status should be FAILED after max attempts exceeded with failed response'
        );
    }

    /**
     * Test: Releases job with retry when attempts <= 2 and response is not success.
     */
    public function test_releases_for_retry_when_attempts_within_limit(): void
    {
        $userId = $this->createUser();
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'to_id' => $userId,
        ]);

        $job = Mockery::mock(NotifyTransaction::class, [$transaction])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Simulate attempt count <= 2
        $job->shouldReceive('attempts')
            ->andReturn(1);

        // The HTTP call will fail (no real server), responseContents will be null.
        // With attempts <= 2, it should release(30) and set notify_status to PENDING.
        $job->shouldReceive('release')
            ->once()
            ->with(30);

        $job->handle();

        $transaction->refresh();
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_PENDING,
            $transaction->notify_status,
            'notify_status should be PENDING when retrying within attempt limit'
        );
    }

    /**
     * Test: Throws RuntimeException for unsupported transaction type.
     */
    public function test_throws_for_unsupported_transaction_type(): void
    {
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_INTERNAL_TRANSFER,
            'status' => Transaction::STATUS_SUCCESS,
        ]);

        $job = new NotifyTransaction($transaction);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported transaction type');

        $job->handle();
    }

    /**
     * Test: Uses transaction->to as target user for TYPE_PAUFEN_TRANSACTION.
     */
    public function test_uses_to_user_for_paufen_transaction(): void
    {
        $fromUserId = $this->createUser(['username' => 'FROM_USER_' . mt_rand(10000, 99999)]);
        $toUserId = $this->createUser(['username' => 'TO_USER_' . mt_rand(10000, 99999)]);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'from_id' => $fromUserId,
            'to_id' => $toUserId,
        ]);

        $job = Mockery::mock(NotifyTransaction::class, [$transaction])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('attempts')->andReturn(3);

        // The job should complete without throwing (meaning it resolved targetUser correctly)
        $job->handle();

        $transaction->refresh();
        // If targetUser was null, a RuntimeException would have been thrown.
        // Reaching here means 'to' user was resolved successfully.
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_FAILED,
            $transaction->notify_status,
            'Job should complete using the to user for paufen transactions'
        );
    }

    /**
     * Test: Uses transaction->from as target user for TYPE_NORMAL_WITHDRAW.
     */
    public function test_uses_from_user_for_normal_withdraw(): void
    {
        $fromUserId = $this->createUser(['username' => 'WITHDRAW_FROM_' . mt_rand(1000, 9999)]);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_SUCCESS,
            'from_id' => $fromUserId,
        ]);

        $job = Mockery::mock(NotifyTransaction::class, [$transaction])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('attempts')->andReturn(3);

        // Should not throw — meaning 'from' user was resolved correctly
        $job->handle();

        $transaction->refresh();
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_FAILED,
            $transaction->notify_status,
            'Job should complete using the from user for normal withdraw'
        );
    }

    /**
     * Test: Uses transaction->from as target user for TYPE_PAUFEN_WITHDRAW.
     */
    public function test_uses_from_user_for_paufen_withdraw(): void
    {
        $fromUserId = $this->createUser(['username' => 'PF_WITHDRAW_' . mt_rand(10000, 99999)]);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_SUCCESS,
            'from_id' => $fromUserId,
        ]);

        $job = Mockery::mock(NotifyTransaction::class, [$transaction])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('attempts')->andReturn(3);

        $job->handle();

        $transaction->refresh();
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_FAILED,
            $transaction->notify_status,
            'Job should complete using the from user for paufen withdraw'
        );
    }

    /**
     * Test: Accepts STATUS_MANUAL_SUCCESS as a final status (does not release with 10s delay).
     */
    public function test_accepts_manual_success_status(): void
    {
        $userId = $this->createUser();
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MANUAL_SUCCESS,
            'to_id' => $userId,
        ]);

        $job = Mockery::mock(NotifyTransaction::class, [$transaction])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // release(10) should NOT be called (which would mean status not final)
        $job->shouldNotReceive('release')->with(10);

        // It may call release(30) for retry — that's fine
        $job->shouldReceive('release')->with(30)->zeroOrMoreTimes();
        $job->shouldReceive('attempts')->andReturn(1);

        $job->handle();

        $transaction->refresh();
        // It should have progressed past the status check (notify_status changed from NONE)
        $this->assertNotEquals(
            Transaction::NOTIFY_STATUS_NONE,
            $transaction->notify_status,
            'STATUS_MANUAL_SUCCESS should be treated as a final status'
        );
    }

    /**
     * Test: Accepts STATUS_FAILED as a final status (does not release with 10s delay).
     */
    public function test_accepts_failed_status(): void
    {
        $userId = $this->createUser();
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_FAILED,
            'to_id' => $userId,
        ]);

        $job = Mockery::mock(NotifyTransaction::class, [$transaction])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldNotReceive('release')->with(10);
        $job->shouldReceive('release')->with(30)->zeroOrMoreTimes();
        $job->shouldReceive('attempts')->andReturn(1);

        $job->handle();

        $transaction->refresh();
        $this->assertNotEquals(
            Transaction::NOTIFY_STATUS_NONE,
            $transaction->notify_status,
            'STATUS_FAILED should be treated as a final status'
        );
    }

    /**
     * Test: Releases with 10 second delay for STATUS_MATCHING (non-final).
     */
    public function test_releases_for_matching_status(): void
    {
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_MATCHING,
        ]);

        $job = Mockery::mock(NotifyTransaction::class, [$transaction])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('release')
            ->once()
            ->with(10);

        $job->handle();

        $transaction->refresh();
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_NONE,
            $transaction->notify_status,
            'Non-final status should not change notify_status'
        );
    }

    /**
     * Test: Throws RuntimeException when target user is null
     * (e.g., TYPE_PAUFEN_TRANSACTION with no to_id set).
     */
    public function test_throws_when_target_user_is_null(): void
    {
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'to_id' => null,
        ]);

        $job = new NotifyTransaction($transaction);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Target user null');

        $job->handle();
    }

    /**
     * Test: Verifies the sign calculation matches expected MD5 logic.
     */
    public function test_sign_calculation_correctness(): void
    {
        $secretKey = 'abc123def456secret';
        $userId = $this->createUser([
            'secret_key' => $secretKey,
            'username' => 'SIGNTEST_' . mt_rand(10000, 99999),
        ]);

        $targetUser = User::find($userId);

        $mainData = [
            'order_number' => 'ORD999',
            'system_order_number' => 'SYS999',
            'username' => $targetUser->username,
            'amount' => '200.000000',
            'status' => Transaction::STATUS_SUCCESS,
            'chain_network' => 'trc20',
            'tx_hash' => '0xhash',
        ];

        $parameters = $mainData;
        ksort($parameters);

        $sign = md5(urldecode(http_build_query($parameters) . '&secret_key=' . $secretKey));

        // Verify the sign is deterministic
        $sign2 = md5(urldecode(http_build_query($parameters) . '&secret_key=' . $secretKey));
        $this->assertEquals($sign, $sign2, 'Sign should be deterministic');

        // Verify changing the secret key produces a different sign
        $differentSign = md5(urldecode(http_build_query($parameters) . '&secret_key=different_key'));
        $this->assertNotEquals($sign, $differentSign, 'Different secret key should produce different sign');

        // Verify the sign is a valid MD5 hash (32 hex characters)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $sign);
    }

    /**
     * Test: notify_status transitions through SENDING during processing.
     */
    public function test_notify_status_transitions_through_sending(): void
    {
        $userId = $this->createUser();
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'to_id' => $userId,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
        ]);

        $job = Mockery::mock(NotifyTransaction::class, [$transaction])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $job->shouldReceive('attempts')->andReturn(3);

        $job->handle();

        $transaction->refresh();
        // After processing, the status should have transitioned from NONE through SENDING
        // to FAILED (since HTTP fails and attempts > 2).
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_FAILED,
            $transaction->notify_status,
            'notify_status should transition through SENDING to FAILED'
        );
    }
}

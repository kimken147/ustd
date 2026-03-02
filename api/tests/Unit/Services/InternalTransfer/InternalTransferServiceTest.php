<?php

namespace Tests\Unit\Services\InternalTransfer;

use App\DTOs\TransactionParams;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Services\InternalTransfer\InternalTransferService;
use App\Utils\BCMathUtil;
use App\Utils\BankCardTransferObject;
use App\Utils\TransactionFactory;
use App\Utils\UserChannelAccountUtil;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InternalTransferServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BCMathUtil $bcMath;
    private $transactionFactory;
    private $userChannelAccountUtil;
    private InternalTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bcMath = new BCMathUtil();
        $this->transactionFactory = Mockery::mock(TransactionFactory::class);
        $this->userChannelAccountUtil = Mockery::mock(UserChannelAccountUtil::class);

        $this->service = new InternalTransferService(
            $this->bcMath,
            $this->transactionFactory,
            $this->userChannelAccountUtil,
        );
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function createUser(array $overrides = []): User
    {
        $defaults = [
            'name' => 'TestUser',
            'username' => 'TESTUSER' . mt_rand(100000, 999999),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'role' => User::ROLE_MERCHANT,
            'secret_key' => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('users')->insertGetId(array_merge($defaults, $overrides));

        return User::find($id);
    }

    private function createUserChannelAccount(int $userId, array $overrides = []): UserChannelAccount
    {
        $defaults = [
            'user_id' => $userId,
            'account' => 'ACC_' . uniqid(),
            'status' => UserChannelAccount::STATUS_ONLINE,
            'detail' => json_encode(['bank_name' => 'ICBC']),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('user_channel_accounts')->insertGetId(array_merge($defaults, $overrides));

        return UserChannelAccount::find($id);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        $defaults = [
            'from_id' => 0,
            'to_id' => 0,
            'type' => Transaction::TYPE_INTERNAL_TRANSFER,
            'status' => Transaction::STATUS_MATCHING,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
            'from_channel_account' => json_encode([]),
            'to_channel_account' => json_encode([]),
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'actual_amount' => '0.00',
            'order_number' => 'TEST' . uniqid(),
            'system_order_number' => 'SYS' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('transactions')->insertGetId(array_merge($defaults, $overrides));

        return Transaction::find($id);
    }

    private function makeRequest(array $data = []): Request
    {
        $defaults = [
            'account_id' => 1,
            'amount' => '100.00',
            'bank_name' => 'ICBC',
            'bank_card_number' => '6222021234567890',
            'bank_card_holder_name' => 'Zhang San',
        ];

        return new Request(array_merge($defaults, $data));
    }

    // ---------------------------------------------------------------
    //  execute() — happy path
    // ---------------------------------------------------------------

    public function test_execute_happy_path_creates_transaction_and_updates_total(): void
    {
        $user = $this->createUser();
        $account = $this->createUserChannelAccount($user->id);

        $transaction = $this->createTransaction([
            'amount' => '100.00',
            'to_channel_account_id' => $account->id,
            'status' => Transaction::STATUS_MATCHING,
        ]);

        $request = $this->makeRequest([
            'account_id' => $account->id,
            'amount' => '100.00',
            'bank_name' => 'ICBC',
            'bank_card_number' => '6222021234567890',
            'bank_card_holder_name' => 'Zhang San',
        ]);

        $this->transactionFactory->shouldReceive('internalTransferFrom')
            ->once()
            ->with(
                Mockery::on(function (TransactionParams $params) {
                    return $params->amount === '100.00'
                        && $params->bankCard instanceof BankCardTransferObject
                        && $params->bankCard->bankName === 'ICBC'
                        && $params->bankCard->bankCardNumber === '6222021234567890'
                        && $params->bankCard->bankCardHolderName === 'Zhang San';
                }),
                Mockery::on(function (UserChannelAccount $arg) use ($account) {
                    return $arg->id === $account->id;
                })
            )
            ->andReturn($transaction);

        $this->userChannelAccountUtil->shouldReceive('updateTotal')
            ->once()
            ->with($account->id, $transaction->amount, true);

        $result = $this->service->execute($request);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertEquals($transaction->id, $result->id);
    }

    public function test_execute_passes_order_id_from_request_when_provided(): void
    {
        $user = $this->createUser();
        $account = $this->createUserChannelAccount($user->id);

        $transaction = $this->createTransaction(['amount' => '200.00']);

        $request = $this->makeRequest([
            'account_id' => $account->id,
            'amount' => '200.00',
            'order_id' => 'CUSTOM_ORDER_123',
        ]);

        $this->transactionFactory->shouldReceive('internalTransferFrom')
            ->once()
            ->with(
                Mockery::on(function (TransactionParams $params) {
                    return $params->orderNumber === 'CUSTOM_ORDER_123'
                        && $params->amount === '200.00';
                }),
                Mockery::type(UserChannelAccount::class)
            )
            ->andReturn($transaction);

        $this->userChannelAccountUtil->shouldReceive('updateTotal')
            ->once();

        $result = $this->service->execute($request);

        $this->assertInstanceOf(Transaction::class, $result);
    }

    // ---------------------------------------------------------------
    //  execute() — validation
    // ---------------------------------------------------------------

    public function test_execute_aborts_when_account_not_found(): void
    {
        $request = $this->makeRequest(['account_id' => 999999]);

        $this->expectException(ModelNotFoundException::class);

        $this->service->execute($request);
    }

    public function test_execute_aborts_when_account_has_pending_transfer(): void
    {
        $user = $this->createUser();
        $account = $this->createUserChannelAccount($user->id);

        // Create a recent STATUS_PAYING transaction for this account
        $this->createTransaction([
            'to_channel_account_id' => $account->id,
            'status' => Transaction::STATUS_PAYING,
            'created_at' => now(),
        ]);

        $request = $this->makeRequest(['account_id' => $account->id]);

        $this->expectException(HttpException::class);

        try {
            $this->service->execute($request);
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_execute_aborts_when_factory_returns_null(): void
    {
        $user = $this->createUser();
        $account = $this->createUserChannelAccount($user->id);

        $request = $this->makeRequest(['account_id' => $account->id]);

        $this->transactionFactory->shouldReceive('internalTransferFrom')
            ->once()
            ->andReturn(null);

        $this->expectException(HttpException::class);

        try {
            $this->service->execute($request);
        } catch (HttpException $e) {
            $this->assertEquals(400, $e->getStatusCode());
            throw $e;
        }
    }

    // ---------------------------------------------------------------
    //  execute() — edge cases
    // ---------------------------------------------------------------

    public function test_execute_allows_when_pending_transfer_is_old(): void
    {
        $user = $this->createUser();
        $account = $this->createUserChannelAccount($user->id);

        // Create an OLD STATUS_PAYING transaction (more than 24h ago)
        $this->createTransaction([
            'to_channel_account_id' => $account->id,
            'status' => Transaction::STATUS_PAYING,
            'created_at' => now()->subDays(2),
        ]);

        $transaction = $this->createTransaction(['amount' => '100.00']);
        $request = $this->makeRequest(['account_id' => $account->id]);

        $this->transactionFactory->shouldReceive('internalTransferFrom')
            ->once()
            ->andReturn($transaction);

        $this->userChannelAccountUtil->shouldReceive('updateTotal')
            ->once();

        $result = $this->service->execute($request);

        $this->assertInstanceOf(Transaction::class, $result);
    }

    public function test_execute_allows_when_existing_transfer_is_not_paying(): void
    {
        $user = $this->createUser();
        $account = $this->createUserChannelAccount($user->id);

        // Create a recent transaction that is STATUS_SUCCESS (not STATUS_PAYING)
        $this->createTransaction([
            'to_channel_account_id' => $account->id,
            'status' => Transaction::STATUS_SUCCESS,
            'created_at' => now(),
        ]);

        $transaction = $this->createTransaction(['amount' => '100.00']);
        $request = $this->makeRequest(['account_id' => $account->id]);

        $this->transactionFactory->shouldReceive('internalTransferFrom')
            ->once()
            ->andReturn($transaction);

        $this->userChannelAccountUtil->shouldReceive('updateTotal')
            ->once();

        $result = $this->service->execute($request);

        $this->assertInstanceOf(Transaction::class, $result);
    }

    public function test_execute_passes_note_from_request(): void
    {
        $user = $this->createUser();
        $account = $this->createUserChannelAccount($user->id);

        $transaction = $this->createTransaction(['amount' => '100.00']);

        $request = $this->makeRequest([
            'account_id' => $account->id,
            'note' => 'test note for transfer',
        ]);

        $this->transactionFactory->shouldReceive('internalTransferFrom')
            ->once()
            ->with(
                Mockery::on(function (TransactionParams $params) {
                    return $params->note === 'test note for transfer';
                }),
                Mockery::type(UserChannelAccount::class)
            )
            ->andReturn($transaction);

        $this->userChannelAccountUtil->shouldReceive('updateTotal')
            ->once();

        $result = $this->service->execute($request);

        $this->assertInstanceOf(Transaction::class, $result);
    }
}

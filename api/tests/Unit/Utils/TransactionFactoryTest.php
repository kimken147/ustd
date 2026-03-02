<?php

namespace Tests\Unit\Utils;

use App\DTOs\TransactionParams;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Models\Wallet;
use App\Services\Transaction\TransactionFeeService;
use App\Utils\BankCardTransferObject;
use App\Utils\TransactionDataBuilder;
use App\Utils\TransactionFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class TransactionFactoryTest extends TestCase
{
    use DatabaseTransactions;

    private $transactionFeeService;
    private $dataBuilder;
    private TransactionFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionFeeService = Mockery::mock(TransactionFeeService::class);
        $this->dataBuilder = Mockery::mock(TransactionDataBuilder::class);

        $this->factory = new TransactionFactory(
            $this->transactionFeeService,
            $this->dataBuilder
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
            'status' => User::STATUS_ENABLE,
            'account_mode' => User::ACCOUNT_MODE_GENERAL,
            'transaction_enable' => true,
            'balance_limit' => 0,
            'secret_key' => 'test_secret_key_' . mt_rand(100000, 999999),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('users')->insertGetId(array_merge($defaults, $overrides));

        return User::find($id);
    }

    private function createWallet(int $userId, array $overrides = []): Wallet
    {
        $defaults = [
            'user_id' => $userId,
            'balance' => '10000.00',
            'profit' => '500.00',
            'frozen_balance' => '0.00',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $id = DB::table('wallets')->insertGetId(array_merge($defaults, $overrides));

        return Wallet::find($id);
    }

    private function createUserWithWallet(array $userOverrides = [], array $walletOverrides = []): User
    {
        $user = $this->createUser($userOverrides);
        $this->createWallet($user->getKey(), $walletOverrides);

        // Reload to pick up wallet relationship
        return User::find($user->getKey());
    }

    private function createChannel(array $overrides = []): Channel
    {
        $defaults = [
            'code' => 'TC_' . strtoupper(uniqid()),
            'name' => 'Test Channel',
            'status' => Channel::STATUS_ENABLE,
            'order_timeout' => 300,
            'order_timeout_enable' => true,
            'transaction_timeout' => 600,
            'transaction_timeout_enable' => true,
            'floating' => 0,
            'floating_enable' => false,
            'present_result' => Channel::RESPONSE_BANK_CARD,
            'real_name_enable' => false,
            'note_enable' => false,
            'max_one_ignore_amount' => false,
            'country' => 'cn',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $data = array_merge($defaults, $overrides);
        DB::table('channels')->insert($data);

        return Channel::find($data['code']);
    }

    private function makeBankCard(): BankCardTransferObject
    {
        $bc = new BankCardTransferObject();
        $bc->bankName = 'ICBC';
        $bc->bankCardNumber = '6222000000001234';
        $bc->bankCardHolderName = 'Test User';
        $bc->bankProvince = 'Beijing';
        $bc->bankCity = 'Beijing';

        return $bc;
    }

    private function makeTransactionData(User $user, array $overrides = []): array
    {
        $wallet = $user->wallet;

        $defaults = [
            'from_id' => 0,
            'to_id' => $user->getKey(),
            'to_wallet_id' => $wallet->getKey(),
            'type' => Transaction::TYPE_NORMAL_DEPOSIT,
            'status' => Transaction::STATUS_PAYING,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
            'from_channel_account' => [],
            'to_channel_account' => [],
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'actual_amount' => 0,
            'order_number' => 'ORD' . mt_rand(100000, 999999),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return array_merge($defaults, $overrides);
    }

    // ---------------------------------------------------------------
    //  throwIfMissing (tested via public methods)
    // ---------------------------------------------------------------

    public function test_throwIfMissing_throws_when_bankCard_is_null(): void
    {
        $user = $this->createUserWithWallet();
        $params = new TransactionParams(
            amount: '100.00',
            bankCard: null,
        );

        // throwIfMissing is called BEFORE try/catch, so RuntimeException propagates
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bankCard can not be empty');

        $this->factory->normalDepositTo($params, $user);
    }

    public function test_throwIfMissing_throws_when_bankCard_property_is_null(): void
    {
        $user = $this->createUserWithWallet();

        $bc = new BankCardTransferObject();
        $bc->bankName = null; // Required property is null
        $bc->bankCardNumber = '6222000000001234';
        $bc->bankCardHolderName = 'Test User';
        $bc->bankProvince = 'Beijing';
        $bc->bankCity = 'Beijing';

        $params = new TransactionParams(
            amount: '100.00',
            bankCard: $bc,
        );

        // throwIfMissing is called BEFORE try/catch, so RuntimeException propagates
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bankCard can not be empty');

        $this->factory->normalDepositTo($params, $user);
    }

    public function test_throwIfMissing_throws_when_clientIpv4_is_null_for_paufen(): void
    {
        $user = $this->createUserWithWallet();
        $channel = $this->createChannel();

        $params = new TransactionParams(
            amount: '100.00',
            clientIpv4: null,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('clientIpv4 can not be empty');

        // paufenTransactionTo uses DB::transaction (not try/catch), so exception propagates
        $this->factory->paufenTransactionTo($params, $user, $channel);
    }

    // ---------------------------------------------------------------
    //  normalDepositTo()
    // ---------------------------------------------------------------

    public function test_normalDepositTo_creates_transaction_and_fees(): void
    {
        $user = $this->createUserWithWallet(['role' => User::ROLE_PROVIDER]);
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '100.00',
            bankCard: $bankCard,
            orderNumber: 'ORD_TEST_001',
        );

        $txData = $this->makeTransactionData($user, [
            'from_channel_account' => $bankCard->toFromChannelAccount(),
            'order_number' => 'ORD_TEST_001',
        ]);

        $this->dataBuilder->shouldReceive('buildNormalDeposit')
            ->once()
            ->with(Mockery::type(TransactionParams::class), Mockery::type(User::class))
            ->andReturn($txData);

        $this->transactionFeeService->shouldReceive('createDepositFees')
            ->once()
            ->with(Mockery::type(Transaction::class), Mockery::type(User::class));

        $result = $this->factory->normalDepositTo($params, $user);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertNotNull($result->getKey());
        $this->assertSame(Transaction::TYPE_NORMAL_DEPOSIT, $result->type);
        $this->assertSame('100.00', $result->amount);
    }

    public function test_normalDepositTo_creates_note_when_provided(): void
    {
        $user = $this->createUserWithWallet(['role' => User::ROLE_PROVIDER]);
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '200.00',
            bankCard: $bankCard,
            note: 'Test deposit note',
        );

        $txData = $this->makeTransactionData($user, [
            'from_channel_account' => $bankCard->toFromChannelAccount(),
            'amount' => '200.00',
            'floating_amount' => '200.00',
            'note' => 'Test deposit note',
        ]);

        $this->dataBuilder->shouldReceive('buildNormalDeposit')
            ->once()
            ->andReturn($txData);

        $this->transactionFeeService->shouldReceive('createDepositFees')
            ->once();

        $result = $this->factory->normalDepositTo($params, $user);

        $this->assertInstanceOf(Transaction::class, $result);

        $note = TransactionNote::where('transaction_id', $result->getKey())->first();
        $this->assertNotNull($note);
        $this->assertSame('Test deposit note', $note->note);
        $this->assertSame($user->getKey(), $note->user_id);
    }

    public function test_normalDepositTo_returns_null_on_runtime_exception(): void
    {
        $user = $this->createUserWithWallet(['role' => User::ROLE_PROVIDER]);
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '100.00',
            bankCard: $bankCard,
        );

        $this->dataBuilder->shouldReceive('buildNormalDeposit')
            ->once()
            ->andThrow(new \RuntimeException('test error'));

        $result = $this->factory->normalDepositTo($params, $user);

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    //  normalWithdrawFrom()
    // ---------------------------------------------------------------

    public function test_normalWithdrawFrom_creates_transaction_and_fees(): void
    {
        $user = $this->createUserWithWallet();
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '500.00',
            bankCard: $bankCard,
            orderNumber: 'ORD_WD_001',
        );

        $txData = $this->makeTransactionData($user, [
            'from_id' => $user->getKey(),
            'from_wallet_id' => $user->wallet->getKey(),
            'to_id' => 0,
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'from_channel_account' => $bankCard->toFromChannelAccount(),
            'amount' => '500.00',
            'floating_amount' => '500.00',
            'order_number' => 'ORD_WD_001',
        ]);
        // Remove to_wallet_id since withdraw doesn't set it
        unset($txData['to_wallet_id']);

        $this->dataBuilder->shouldReceive('buildNormalWithdraw')
            ->once()
            ->with(Mockery::type(TransactionParams::class), Mockery::type(User::class))
            ->andReturn($txData);

        $this->transactionFeeService->shouldReceive('createWithdrawFees')
            ->once()
            ->with(
                Mockery::type(Transaction::class),
                Mockery::type(User::class),
                false,
                null,
                'balance'
            );

        $result = $this->factory->normalWithdrawFrom($params, $user);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertSame(Transaction::TYPE_NORMAL_WITHDRAW, $result->type);
        $this->assertSame('500.00', $result->amount);
    }

    public function test_normalWithdrawFrom_returns_null_on_exception(): void
    {
        $user = $this->createUserWithWallet();
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '500.00',
            bankCard: $bankCard,
        );

        $this->dataBuilder->shouldReceive('buildNormalWithdraw')
            ->once()
            ->andThrow(new \RuntimeException('withdraw error'));

        $result = $this->factory->normalWithdrawFrom($params, $user);

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    //  paufenWithdrawFrom()
    // ---------------------------------------------------------------

    public function test_paufenWithdrawFrom_creates_transaction_and_fees(): void
    {
        $merchant = $this->createUserWithWallet();
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '800.00',
            bankCard: $bankCard,
            orderNumber: 'ORD_PW_001',
        );

        $txData = $this->makeTransactionData($merchant, [
            'from_id' => $merchant->getKey(),
            'from_wallet_id' => $merchant->wallet->getKey(),
            'to_id' => null,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_MATCHING,
            'from_channel_account' => $bankCard->toFromChannelAccount(),
            'amount' => '800.00',
            'floating_amount' => '800.00',
            'order_number' => 'ORD_PW_001',
        ]);
        unset($txData['to_wallet_id']);

        $this->dataBuilder->shouldReceive('buildPaufenWithdraw')
            ->once()
            ->with(Mockery::type(TransactionParams::class), Mockery::type(User::class))
            ->andReturn($txData);

        $this->transactionFeeService->shouldReceive('createWithdrawFees')
            ->once()
            ->with(
                Mockery::type(Transaction::class),
                Mockery::type(User::class),
                false,
                null
            );

        $result = $this->factory->paufenWithdrawFrom($params, $merchant);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertSame(Transaction::TYPE_PAUFEN_WITHDRAW, $result->type);
        $this->assertSame('800.00', $result->amount);
    }

    public function test_paufenWithdrawFrom_returns_null_on_exception(): void
    {
        $merchant = $this->createUserWithWallet();
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '800.00',
            bankCard: $bankCard,
        );

        $this->dataBuilder->shouldReceive('buildPaufenWithdraw')
            ->once()
            ->andThrow(new \RuntimeException('paufen withdraw error'));

        $result = $this->factory->paufenWithdrawFrom($params, $merchant);

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    //  thirdchannelWithdrawFrom()
    // ---------------------------------------------------------------

    public function test_thirdchannelWithdrawFrom_creates_transaction_and_fees(): void
    {
        $user = $this->createUserWithWallet();
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '1000.00',
            bankCard: $bankCard,
            orderNumber: 'ORD_TC_001',
        );

        $txData = $this->makeTransactionData($user, [
            'from_id' => $user->getKey(),
            'from_wallet_id' => $user->wallet->getKey(),
            'to_id' => 0,
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_THIRD_PAYING,
            'from_channel_account' => $bankCard->toFromChannelAccount(),
            'amount' => '1000.00',
            'floating_amount' => '1000.00',
            'order_number' => 'ORD_TC_001',
            'thirdchannel_id' => 42,
        ]);
        unset($txData['to_wallet_id']);

        $this->dataBuilder->shouldReceive('buildThirdchannelWithdraw')
            ->once()
            ->with(
                Mockery::type(TransactionParams::class),
                Mockery::type(User::class),
                42
            )
            ->andReturn($txData);

        $this->transactionFeeService->shouldReceive('createWithdrawFees')
            ->once()
            ->with(
                Mockery::type(Transaction::class),
                Mockery::type(User::class),
                false,
                null
            );

        $result = $this->factory->thirdchannelWithdrawFrom($params, $user, false, null, 42);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertSame(Transaction::TYPE_NORMAL_WITHDRAW, $result->type);
        $this->assertSame(Transaction::STATUS_THIRD_PAYING, $result->status);
        $this->assertSame('1000.00', $result->amount);
        $this->assertSame(42, $result->thirdchannel_id);
    }

    public function test_thirdchannelWithdrawFrom_returns_null_on_exception(): void
    {
        $user = $this->createUserWithWallet();
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '1000.00',
            bankCard: $bankCard,
        );

        $this->dataBuilder->shouldReceive('buildThirdchannelWithdraw')
            ->once()
            ->andThrow(new \RuntimeException('thirdchannel error'));

        $result = $this->factory->thirdchannelWithdrawFrom($params, $user, false, null, 99);

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    //  internalTransferFrom()
    // ---------------------------------------------------------------

    public function test_internalTransferFrom_creates_transaction_without_fees(): void
    {
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '300.00',
            bankCard: $bankCard,
            orderNumber: 'ORD_IT_001',
        );

        $txData = [
            'from_id' => 0,
            'from_wallet_id' => 0,
            'to_id' => 0,
            'to_channel_account_id' => null,
            'type' => Transaction::TYPE_INTERNAL_TRANSFER,
            'status' => Transaction::STATUS_MATCHING,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
            'to_account_mode' => null,
            'from_channel_account' => $bankCard->toFromChannelAccount(false),
            'to_channel_account' => [],
            'amount' => '300.00',
            'floating_amount' => '300.00',
            'actual_amount' => 0,
            'usdt_rate' => 0,
            'channel_code' => null,
            'order_number' => 'ORD_IT_001',
            'note' => null,
        ];

        $this->dataBuilder->shouldReceive('buildInternalTransfer')
            ->once()
            ->with(Mockery::type(TransactionParams::class), null)
            ->andReturn($txData);

        // transactionFeeService should NOT be called at all
        $this->transactionFeeService->shouldNotReceive('createDepositFees');
        $this->transactionFeeService->shouldNotReceive('createWithdrawFees');

        $result = $this->factory->internalTransferFrom($params);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertSame(Transaction::TYPE_INTERNAL_TRANSFER, $result->type);
        $this->assertSame(Transaction::STATUS_MATCHING, $result->status);
        $this->assertSame('300.00', $result->amount);
    }

    public function test_internalTransferFrom_returns_null_on_exception(): void
    {
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '300.00',
            bankCard: $bankCard,
        );

        $this->dataBuilder->shouldReceive('buildInternalTransfer')
            ->once()
            ->andThrow(new \RuntimeException('internal transfer error'));

        $result = $this->factory->internalTransferFrom($params);

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    //  paufenTransactionTo()
    // ---------------------------------------------------------------

    public function test_paufenTransactionTo_creates_transaction_with_note_disabled(): void
    {
        $merchant = $this->createUserWithWallet();
        $channel = $this->createChannel(['note_enable' => false]);
        $bankCard = $this->makeBankCard();

        $params = new TransactionParams(
            amount: '600.00',
            clientIpv4: '192.168.1.1',
            orderNumber: 'ORD_PT_001',
            notifyUrl: 'https://example.com/notify',
        );

        $txData = $this->makeTransactionData($merchant, [
            'from_id' => null,
            'to_id' => $merchant->getKey(),
            'to_wallet_id' => $merchant->wallet->getKey(),
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'from_channel_account' => [],
            'to_channel_account' => ['query' => '[]'],
            'amount' => '600.00',
            'floating_amount' => '600.00',
            'channel_code' => $channel->getKey(),
            'order_number' => 'ORD_PT_001',
            'notify_url' => 'https://example.com/notify',
            'client_ipv4' => '192.168.1.1',
        ]);

        $this->dataBuilder->shouldReceive('buildPaufenTransaction')
            ->once()
            ->with(
                Mockery::type(TransactionParams::class),
                Mockery::type(User::class),
                Mockery::type(Channel::class)
            )
            ->andReturn($txData);

        $result = $this->factory->paufenTransactionTo($params, $merchant, $channel);

        $this->assertInstanceOf(Transaction::class, $result);
        $this->assertSame(Transaction::TYPE_PAUFEN_TRANSACTION, $result->type);
        $this->assertSame('600.00', $result->amount);
        // note_enable=false so note should not be auto-generated
        $this->assertNull($result->note);
    }

    public function test_paufenTransactionTo_creates_transaction_with_note_enabled_cn(): void
    {
        $merchant = $this->createUserWithWallet();
        $channel = $this->createChannel([
            'note_enable' => true,
            'country' => 'cn',
        ]);

        $params = new TransactionParams(
            amount: '700.00',
            clientIpv4: '10.0.0.1',
            orderNumber: 'ORD_PT_002',
        );

        $txData = $this->makeTransactionData($merchant, [
            'from_id' => null,
            'to_id' => $merchant->getKey(),
            'to_wallet_id' => $merchant->wallet->getKey(),
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'from_channel_account' => [],
            'to_channel_account' => ['query' => '[]'],
            'amount' => '700.00',
            'floating_amount' => '700.00',
            'channel_code' => $channel->getKey(),
            'order_number' => 'ORD_PT_002',
            'client_ipv4' => '10.0.0.1',
        ]);

        $this->dataBuilder->shouldReceive('buildPaufenTransaction')
            ->once()
            ->andReturn($txData);

        $result = $this->factory->paufenTransactionTo($params, $merchant, $channel);

        $this->assertInstanceOf(Transaction::class, $result);
        // note_enable=true + country=cn → random 6-digit note (not from system_order_number)
        $this->assertNotNull($result->note);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result->note);
    }

    public function test_paufenTransactionTo_creates_transaction_with_note_enabled_vn(): void
    {
        $merchant = $this->createUserWithWallet();
        $channel = $this->createChannel([
            'note_enable' => true,
            'country' => 'vn',
        ]);

        $params = new TransactionParams(
            amount: '500.00',
            clientIpv4: '10.0.0.2',
            orderNumber: 'ORD_PT_003',
        );

        $txData = $this->makeTransactionData($merchant, [
            'from_id' => null,
            'to_id' => $merchant->getKey(),
            'to_wallet_id' => $merchant->wallet->getKey(),
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'from_channel_account' => [],
            'to_channel_account' => ['query' => '[]'],
            'amount' => '500.00',
            'floating_amount' => '500.00',
            'channel_code' => $channel->getKey(),
            'order_number' => 'ORD_PT_003',
            'client_ipv4' => '10.0.0.2',
        ]);

        $this->dataBuilder->shouldReceive('buildPaufenTransaction')
            ->once()
            ->andReturn($txData);

        $result = $this->factory->paufenTransactionTo($params, $merchant, $channel);

        $this->assertInstanceOf(Transaction::class, $result);
        // note_enable=true + country=vn → last 6 chars of system_order_number
        $this->assertNotNull($result->note);
        $expected = substr($result->system_order_number, -6);
        $this->assertSame($expected, $result->note);
    }
}

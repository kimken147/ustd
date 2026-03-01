<?php

namespace Tests\Unit\Services\Transaction;

use App\Models\BannedIp;
use App\Models\BannedRealname;
use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannel;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\DTO\CreateTransactionContext;
use App\Services\Transaction\Exceptions\TransactionValidationException;
use App\Services\Transaction\TransactionValidationService;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\SignatureCalculator;
use App\Utils\WhitelistedIpManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class TransactionValidationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BCMathUtil $bcMath;
    private $featureToggleRepository;
    private $notificationUtil;
    private $whitelistedIpManager;
    private TransactionValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bcMath = new BCMathUtil();
        $this->featureToggleRepository = Mockery::mock(FeatureToggleRepository::class);
        $this->notificationUtil = Mockery::mock(NotificationUtil::class);
        $this->whitelistedIpManager = Mockery::mock(WhitelistedIpManager::class);

        $this->service = new TransactionValidationService(
            $this->bcMath,
            $this->featureToggleRepository,
            $this->notificationUtil,
            $this->whitelistedIpManager,
        );
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function createUser(array $overrides = []): int
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
            'secret_key' => 'test_secret_key_123',
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
        ];

        return DB::table('users')->insertGetId(array_merge($defaults, $overrides));
    }

    private function createChannel(array $overrides = []): string
    {
        $defaults = [
            'code' => 'TEST_CH_' . mt_rand(1000, 9999),
            'name' => 'Test Channel',
            'status' => Channel::STATUS_ENABLE,
        ];

        $data = array_merge($defaults, $overrides);
        DB::table('channels')->insert($data);

        return $data['code'];
    }

    private function createWallet(int $userId, array $overrides = []): void
    {
        $defaults = [
            'user_id' => $userId,
            'status' => 1,
            'balance' => '1000.00',
            'profit' => '0.00',
            'frozen_balance' => '0.00',
        ];

        DB::table('wallets')->insert(array_merge($defaults, $overrides));
    }

    private function createBannedIp(string $ip): void
    {
        DB::table('banned_ips')->insert([
            'ipv4' => ip2long($ip),
            'type' => BannedIp::TYPE_TRANSACTION,
        ]);
    }

    private function createBannedRealname(string $name): void
    {
        DB::table('banned_realnames')->insert([
            'realname' => $name,
            'type' => BannedRealname::TYPE_TRANSACTION,
        ]);
    }

    private function createChannelGroup(string $channelCode, array $overrides = []): int
    {
        $defaults = [
            'channel_code' => $channelCode,
            'fixed_amount' => false,
        ];

        return DB::table('channel_groups')->insertGetId(array_merge($defaults, $overrides));
    }

    private function createChannelAmount(int $channelGroupId, string $channelCode, array $overrides = []): int
    {
        $defaults = [
            'channel_group_id' => $channelGroupId,
            'channel_code' => $channelCode,
            'min_amount' => '10.00',
            'max_amount' => '1000.00',
        ];

        return DB::table('channel_amounts')->insertGetId(array_merge($defaults, $overrides));
    }

    private function createUserChannel(int $userId, int $channelGroupId, array $overrides = []): int
    {
        $defaults = [
            'user_id' => $userId,
            'channel_group_id' => $channelGroupId,
            'status' => UserChannel::STATUS_ENABLED,
            'min_amount' => null,
            'max_amount' => null,
            'real_name_enable' => false,
        ];

        return DB::table('user_channels')->insertGetId(array_merge($defaults, $overrides));
    }

    private function createTransaction(array $overrides = []): void
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

        DB::table('transactions')->insert(array_merge($defaults, $overrides));
    }

    /**
     * Build a CreateTransactionContext with a valid signature computed from the given params.
     */
    private function makeContext(array $overrides = [], string $secretKey = 'test_secret_key_123'): CreateTransactionContext
    {
        $defaults = [
            'channelCode' => 'TEST_CH',
            'username' => 'TESTUSER',
            'amount' => '100.00',
            'orderNumber' => 'ORD123456',
            'notifyUrl' => 'https://example.com/notify',
            'clientIp' => '192.168.1.1',
            'realName' => null,
            'returnUrl' => null,
            'bankName' => null,
            'usdtRate' => null,
            'matchLastAccount' => null,
            'isThirdParty' => false,
        ];

        $merged = array_merge($defaults, $overrides);

        // Build the params map used for signature calculation
        $params = [
            'channel_code' => $merged['channelCode'],
            'username' => $merged['username'],
            'amount' => $merged['amount'],
            'order_number' => $merged['orderNumber'],
            'notify_url' => $merged['notifyUrl'],
        ];
        if ($merged['clientIp']) {
            $params['client_ip'] = $merged['clientIp'];
        }
        if ($merged['realName']) {
            $params['real_name'] = $merged['realName'];
        }
        if ($merged['returnUrl']) {
            $params['return_url'] = $merged['returnUrl'];
        }
        if ($merged['bankName']) {
            $params['bank_name'] = $merged['bankName'];
        }
        if ($merged['usdtRate']) {
            $params['usdt_rate'] = $merged['usdtRate'];
        }
        if ($merged['matchLastAccount'] !== null) {
            $params['match_last_account'] = $merged['matchLastAccount'];
        }

        $sign = SignatureCalculator::calculate($params, $secretKey);

        return new CreateTransactionContext(
            channelCode: $merged['channelCode'],
            username: $merged['username'],
            amount: $merged['amount'],
            orderNumber: $merged['orderNumber'],
            notifyUrl: $merged['notifyUrl'],
            sign: $sign,
            clientIp: $merged['clientIp'],
            realName: $merged['realName'],
            returnUrl: $merged['returnUrl'],
            bankName: $merged['bankName'],
            usdtRate: $merged['usdtRate'],
            matchLastAccount: $merged['matchLastAccount'],
            isThirdParty: $merged['isThirdParty'],
        );
    }

    // ---------------------------------------------------------------
    //  validateAndGetChannel()
    // ---------------------------------------------------------------

    public function test_validateAndGetChannel_returns_channel_when_exists_and_enabled(): void
    {
        $channelId = $this->createChannel(['code' => 'VALID_CH']);
        $context = $this->makeContext(['channelCode' => 'VALID_CH']);

        $channel = $this->service->validateAndGetChannel($context);

        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertEquals('VALID_CH', $channel->code);
    }

    public function test_validateAndGetChannel_throws_when_channel_not_found(): void
    {
        $context = $this->makeContext(['channelCode' => 'NONEXISTENT']);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetChannel($context);
    }

    public function test_validateAndGetChannel_throws_when_channel_disabled(): void
    {
        $this->createChannel(['code' => 'DISABLED_CH', 'status' => Channel::STATUS_DISABLE]);
        $context = $this->makeContext(['channelCode' => 'DISABLED_CH']);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetChannel($context);
    }

    // ---------------------------------------------------------------
    //  validateSignature()
    // ---------------------------------------------------------------

    public function test_validateSignature_passes_with_correct_sign(): void
    {
        $secretKey = 'my_secret_key';
        $userId = $this->createUser(['secret_key' => $secretKey]);
        $merchant = User::find($userId);

        $context = $this->makeContext([], $secretKey);

        // Should not throw
        $this->service->validateSignature($context, $merchant);
        $this->assertTrue(true);
    }

    public function test_validateSignature_passes_with_sign_excluding_real_name(): void
    {
        $secretKey = 'backward_compat_key';
        $userId = $this->createUser(['secret_key' => $secretKey]);
        $merchant = User::find($userId);

        // Build a sign WITHOUT real_name, but pass real_name in context
        $paramsWithoutRealName = [
            'channel_code' => 'TEST_CH',
            'username' => 'TESTUSER',
            'amount' => '100.00',
            'order_number' => 'ORD123456',
            'notify_url' => 'https://example.com/notify',
            'client_ip' => '192.168.1.1',
        ];
        $signWithoutRealName = SignatureCalculator::calculate($paramsWithoutRealName, $secretKey);

        $context = new CreateTransactionContext(
            channelCode: 'TEST_CH',
            username: 'TESTUSER',
            amount: '100.00',
            orderNumber: 'ORD123456',
            notifyUrl: 'https://example.com/notify',
            sign: $signWithoutRealName,
            clientIp: '192.168.1.1',
            realName: '張三',
        );

        // Should not throw — backward compatible sign accepted
        $this->service->validateSignature($context, $merchant);
        $this->assertTrue(true);
    }

    public function test_validateSignature_throws_with_wrong_sign(): void
    {
        $userId = $this->createUser(['secret_key' => 'correct_key']);
        $merchant = User::find($userId);

        $context = new CreateTransactionContext(
            channelCode: 'TEST_CH',
            username: 'TESTUSER',
            amount: '100.00',
            orderNumber: 'ORD123456',
            notifyUrl: 'https://example.com/notify',
            sign: 'definitely_wrong_sign',
            clientIp: '192.168.1.1',
        );

        $this->expectException(TransactionValidationException::class);
        $this->service->validateSignature($context, $merchant);
    }

    public function test_validateSignature_includes_optional_fields_in_calculation(): void
    {
        $secretKey = 'optional_fields_key';
        $userId = $this->createUser(['secret_key' => $secretKey]);
        $merchant = User::find($userId);

        $context = $this->makeContext([
            'clientIp' => '10.0.0.1',
            'returnUrl' => 'https://example.com/return',
            'bankName' => 'ICBC',
            'usdtRate' => '7.25',
            'matchLastAccount' => true,
        ], $secretKey);

        // Should not throw — sign includes all optional fields
        $this->service->validateSignature($context, $merchant);
        $this->assertTrue(true);
    }

    public function test_validateSignature_is_case_insensitive(): void
    {
        $secretKey = 'case_test_key';
        $userId = $this->createUser(['secret_key' => $secretKey]);
        $merchant = User::find($userId);

        $params = [
            'channel_code' => 'TEST_CH',
            'username' => 'TESTUSER',
            'amount' => '100.00',
            'order_number' => 'ORD123456',
            'notify_url' => 'https://example.com/notify',
            'client_ip' => '192.168.1.1',
        ];
        $sign = strtoupper(SignatureCalculator::calculate($params, $secretKey));

        $context = new CreateTransactionContext(
            channelCode: 'TEST_CH',
            username: 'TESTUSER',
            amount: '100.00',
            orderNumber: 'ORD123456',
            notifyUrl: 'https://example.com/notify',
            sign: $sign,
            clientIp: '192.168.1.1',
        );

        // Should not throw — uppercase sign accepted
        $this->service->validateSignature($context, $merchant);
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    //  validateAndGetMerchant()
    // ---------------------------------------------------------------

    public function test_validateAndGetMerchant_returns_merchant_when_valid(): void
    {
        $secretKey = 'merchant_valid_key';
        $username = 'VALIDMERCHANT' . mt_rand(1000, 9999);
        $userId = $this->createUser([
            'username' => $username,
            'secret_key' => $secretKey,
            'transaction_enable' => true,
            'balance_limit' => 0,
        ]);
        $this->createWallet($userId, ['balance' => '1000.00', 'frozen_balance' => '0.00']);

        $context = $this->makeContext(['username' => $username], $secretKey);

        $this->whitelistedIpManager->shouldReceive('isNotAllowedToUseThirdPartyApi')->never();

        $merchant = $this->service->validateAndGetMerchant($context, new Channel());

        $this->assertInstanceOf(User::class, $merchant);
        $this->assertEquals($userId, $merchant->getKey());
    }

    public function test_validateAndGetMerchant_throws_when_ip_banned(): void
    {
        $clientIp = '1.2.3.4';
        $this->createBannedIp($clientIp);

        $context = $this->makeContext(['clientIp' => $clientIp]);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetMerchant($context, new Channel());
    }

    public function test_validateAndGetMerchant_throws_when_realname_banned(): void
    {
        $this->createBannedRealname('李四');

        $secretKey = 'realname_ban_key';
        $username = 'RNMERCHANT' . mt_rand(1000, 9999);
        $this->createUser([
            'username' => $username,
            'secret_key' => $secretKey,
        ]);

        $context = $this->makeContext([
            'username' => $username,
            'realName' => '李四',
            'clientIp' => '10.10.10.10',
        ], $secretKey);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetMerchant($context, new Channel());
    }

    public function test_validateAndGetMerchant_throws_when_user_not_found(): void
    {
        $context = $this->makeContext([
            'username' => 'NONEXISTENTUSER999',
            'clientIp' => '10.10.10.10',
        ]);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetMerchant($context, new Channel());
    }

    public function test_validateAndGetMerchant_throws_when_user_disabled(): void
    {
        $secretKey = 'disabled_user_key';
        $username = 'DISABLEDUSER' . mt_rand(1000, 9999);
        $this->createUser([
            'username' => $username,
            'secret_key' => $secretKey,
            'status' => User::STATUS_DISABLE,
        ]);

        $context = $this->makeContext([
            'username' => $username,
            'clientIp' => '10.10.10.10',
        ], $secretKey);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetMerchant($context, new Channel());
    }

    public function test_validateAndGetMerchant_throws_when_transaction_disabled(): void
    {
        $secretKey = 'txn_disabled_key';
        $username = 'TXNDISABLED' . mt_rand(1000, 9999);
        $userId = $this->createUser([
            'username' => $username,
            'secret_key' => $secretKey,
            'transaction_enable' => false,
        ]);
        $this->createWallet($userId);

        $context = $this->makeContext(['username' => $username], $secretKey);

        $this->whitelistedIpManager->shouldReceive('isNotAllowedToUseThirdPartyApi')->never();

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetMerchant($context, new Channel());
    }

    public function test_validateAndGetMerchant_throws_when_balance_limit_exceeded(): void
    {
        $secretKey = 'balance_limit_key';
        $username = 'BALANCELIMIT' . mt_rand(1000, 9999);
        $userId = $this->createUser([
            'username' => $username,
            'secret_key' => $secretKey,
            'transaction_enable' => true,
            'balance_limit' => 500, // limit is 500
        ]);
        $this->createWallet($userId, ['balance' => '450.00', 'frozen_balance' => '0.00']);

        // amount=100, available_balance=450 → totalCost=550 > 500
        $context = $this->makeContext([
            'username' => $username,
            'amount' => '100.00',
        ], $secretKey);

        $this->whitelistedIpManager->shouldReceive('isNotAllowedToUseThirdPartyApi')->never();

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetMerchant($context, new Channel());
    }

    public function test_validateAndGetMerchant_passes_when_balance_limit_is_zero(): void
    {
        $secretKey = 'no_limit_key';
        $username = 'NOLIMIT' . mt_rand(1000, 9999);
        $userId = $this->createUser([
            'username' => $username,
            'secret_key' => $secretKey,
            'transaction_enable' => true,
            'balance_limit' => 0,
        ]);
        $this->createWallet($userId, ['balance' => '999999.00', 'frozen_balance' => '0.00']);

        $context = $this->makeContext([
            'username' => $username,
            'amount' => '50000.00',
        ], $secretKey);

        $merchant = $this->service->validateAndGetMerchant($context, new Channel());

        $this->assertEquals($userId, $merchant->getKey());
    }

    public function test_validateAndGetMerchant_throws_when_third_party_ip_not_allowed(): void
    {
        $secretKey = 'tp_ip_key';
        $username = 'TPIPBLOCK' . mt_rand(1000, 9999);
        $userId = $this->createUser([
            'username' => $username,
            'secret_key' => $secretKey,
            'transaction_enable' => true,
        ]);
        $this->createWallet($userId);

        $context = $this->makeContext([
            'username' => $username,
            'isThirdParty' => true,
        ], $secretKey);

        $this->whitelistedIpManager
            ->shouldReceive('isNotAllowedToUseThirdPartyApi')
            ->once()
            ->andReturn(true);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetMerchant($context, new Channel());
    }

    public function test_validateAndGetMerchant_passes_when_third_party_ip_allowed(): void
    {
        $secretKey = 'tp_ok_key';
        $username = 'TPIPALLOW' . mt_rand(1000, 9999);
        $userId = $this->createUser([
            'username' => $username,
            'secret_key' => $secretKey,
            'transaction_enable' => true,
            'balance_limit' => 0,
        ]);
        $this->createWallet($userId);

        $context = $this->makeContext([
            'username' => $username,
            'isThirdParty' => true,
        ], $secretKey);

        $this->whitelistedIpManager
            ->shouldReceive('isNotAllowedToUseThirdPartyApi')
            ->once()
            ->andReturn(false);

        $merchant = $this->service->validateAndGetMerchant($context, new Channel());

        $this->assertEquals($userId, $merchant->getKey());
    }

    public function test_validateAndGetMerchant_extracts_ip_from_request_when_clientIp_is_null(): void
    {
        $secretKey = 'extract_ip_key';
        $username = 'EXTRACTIP' . mt_rand(1000, 9999);
        $userId = $this->createUser([
            'username' => $username,
            'secret_key' => $secretKey,
            'transaction_enable' => true,
            'balance_limit' => 0,
        ]);
        $this->createWallet($userId);

        $context = $this->makeContext([
            'username' => $username,
            'clientIp' => null,
        ], $secretKey);

        $this->whitelistedIpManager
            ->shouldReceive('extractIpFromRequest')
            ->once()
            ->andReturn('10.20.30.40');

        $merchant = $this->service->validateAndGetMerchant($context, new Channel());

        $this->assertEquals($userId, $merchant->getKey());
    }

    // ---------------------------------------------------------------
    //  checkRateLimit()
    // ---------------------------------------------------------------

    public function test_checkRateLimit_skips_when_feature_disabled(): void
    {
        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT)
            ->once()
            ->andReturn(false);

        $userId = $this->createUser();
        $merchant = User::find($userId);
        $context = $this->makeContext();

        // Should not throw
        $this->service->checkRateLimit($context, $merchant);
        $this->assertTrue(true);
    }

    public function test_checkRateLimit_skips_when_ip_invalid(): void
    {
        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT)
            ->andReturn(true);
        $this->featureToggleRepository
            ->shouldReceive('valueOf')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT, 5)
            ->andReturn(5);

        $userId = $this->createUser();
        $merchant = User::find($userId);
        $context = $this->makeContext(['clientIp' => 'invalid_ip']);

        // Should not throw — invalid IP skips check
        $this->service->checkRateLimit($context, $merchant);
        $this->assertTrue(true);
    }

    public function test_checkRateLimit_passes_when_under_limit(): void
    {
        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT)
            ->andReturn(true);
        $this->featureToggleRepository
            ->shouldReceive('valueOf')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT, 5)
            ->andReturn(5);

        $clientIp = '99.99.99.99';
        $userId = $this->createUser();
        $merchant = User::find($userId);

        // Create 4 transactions (under limit of 5)
        for ($i = 0; $i < 4; $i++) {
            $this->createTransaction([
                'client_ipv4' => ip2long($clientIp),
                'status' => Transaction::STATUS_SUCCESS,
                'created_at' => now(),
            ]);
        }

        $context = $this->makeContext(['clientIp' => $clientIp]);

        // Should not throw
        $this->service->checkRateLimit($context, $merchant);
        $this->assertTrue(true);
    }

    public function test_checkRateLimit_throws_when_limit_exceeded(): void
    {
        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT)
            ->andReturn(true);
        $this->featureToggleRepository
            ->shouldReceive('valueOf')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT, 5)
            ->andReturn(3);

        $clientIp = '88.88.88.88';
        $userId = $this->createUser();
        $merchant = User::find($userId);

        // Create 3 transactions (at the limit of 3)
        for ($i = 0; $i < 3; $i++) {
            $this->createTransaction([
                'client_ipv4' => ip2long($clientIp),
                'status' => Transaction::STATUS_SUCCESS,
                'created_at' => now(),
            ]);
        }

        $context = $this->makeContext(['clientIp' => $clientIp]);

        $this->notificationUtil
            ->shouldReceive('notifyBusyPayingBlocked')
            ->once();

        $this->expectException(TransactionValidationException::class);
        $this->service->checkRateLimit($context, $merchant);
    }

    public function test_checkRateLimit_excludes_matching_statuses(): void
    {
        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT)
            ->andReturn(true);
        $this->featureToggleRepository
            ->shouldReceive('valueOf')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT, 5)
            ->andReturn(2);

        $clientIp = '77.77.77.77';
        $userId = $this->createUser();
        $merchant = User::find($userId);

        // MATCHING and MATCHING_TIMED_OUT should NOT count toward rate limit
        $this->createTransaction([
            'client_ipv4' => ip2long($clientIp),
            'status' => Transaction::STATUS_MATCHING,
            'created_at' => now(),
        ]);
        $this->createTransaction([
            'client_ipv4' => ip2long($clientIp),
            'status' => Transaction::STATUS_MATCHING_TIMED_OUT,
            'created_at' => now(),
        ]);
        // Only 1 counted transaction (under limit of 2)
        $this->createTransaction([
            'client_ipv4' => ip2long($clientIp),
            'status' => Transaction::STATUS_SUCCESS,
            'created_at' => now(),
        ]);

        $context = $this->makeContext(['clientIp' => $clientIp]);

        // Should not throw — only 1 non-matching transaction
        $this->service->checkRateLimit($context, $merchant);
        $this->assertTrue(true);
    }

    public function test_checkRateLimit_excludes_old_transactions(): void
    {
        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT)
            ->andReturn(true);
        $this->featureToggleRepository
            ->shouldReceive('valueOf')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT, 5)
            ->andReturn(2);

        $clientIp = '66.66.66.66';
        $userId = $this->createUser();
        $merchant = User::find($userId);

        // Transactions older than 10 minutes should NOT count
        $this->createTransaction([
            'client_ipv4' => ip2long($clientIp),
            'status' => Transaction::STATUS_SUCCESS,
            'created_at' => now()->subMinutes(11),
        ]);
        $this->createTransaction([
            'client_ipv4' => ip2long($clientIp),
            'status' => Transaction::STATUS_SUCCESS,
            'created_at' => now()->subMinutes(15),
        ]);
        // Only 1 recent transaction (under limit of 2)
        $this->createTransaction([
            'client_ipv4' => ip2long($clientIp),
            'status' => Transaction::STATUS_SUCCESS,
            'created_at' => now(),
        ]);

        $context = $this->makeContext(['clientIp' => $clientIp]);

        // Should not throw
        $this->service->checkRateLimit($context, $merchant);
        $this->assertTrue(true);
    }

    public function test_checkRateLimit_extracts_ip_from_request_when_clientIp_is_null(): void
    {
        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT)
            ->andReturn(true);
        $this->featureToggleRepository
            ->shouldReceive('valueOf')
            ->with(FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT, 5)
            ->andReturn(5);

        $userId = $this->createUser();
        $merchant = User::find($userId);

        $this->whitelistedIpManager
            ->shouldReceive('extractIpFromRequest')
            ->once()
            ->andReturn('55.55.55.55');

        $context = $this->makeContext(['clientIp' => null]);

        // Should not throw — no transactions for this IP
        $this->service->checkRateLimit($context, $merchant);
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    //  withSign()
    // ---------------------------------------------------------------

    public function test_withSign_appends_sign_to_collection(): void
    {
        $secretKey = 'sign_append_key';
        $userId = $this->createUser(['secret_key' => $secretKey]);
        $merchant = User::find($userId);

        $postData = new Collection([
            'channel_code' => 'CH1',
            'username' => 'USER1',
            'amount' => '200.00',
        ]);

        $result = $this->service->withSign($postData, $merchant);

        $this->assertArrayHasKey('sign', $result->toArray());

        // Verify the sign is correct
        $expectedSign = strtolower(SignatureCalculator::calculate($postData->toArray(), $secretKey));
        $this->assertEquals($expectedSign, $result['sign']);
    }

    public function test_withSign_filters_null_values_before_signing(): void
    {
        $secretKey = 'null_filter_key';
        $userId = $this->createUser(['secret_key' => $secretKey]);
        $merchant = User::find($userId);

        $postData = new Collection([
            'channel_code' => 'CH1',
            'username' => 'USER1',
            'amount' => '200.00',
            'optional_field' => null,
        ]);

        $result = $this->service->withSign($postData, $merchant);

        // Sign should be based on non-null values only
        $filteredParams = array_filter($postData->toArray());
        $expectedSign = strtolower(SignatureCalculator::calculate($filteredParams, $secretKey));
        $this->assertEquals($expectedSign, $result['sign']);
    }

    // ---------------------------------------------------------------
    //  validateAndGetUserChannel()
    // ---------------------------------------------------------------

    public function test_validateAndGetUserChannel_throws_when_amount_out_of_channel_range(): void
    {
        $channelCode = $this->createChannel(['code' => 'UCAMT_CH']);
        $channel = Channel::find($channelCode);

        $channelGroupId = $this->createChannelGroup($channelCode);
        $this->createChannelAmount($channelGroupId, $channelCode, [
            'min_amount' => '100.00',
            'max_amount' => '500.00',
        ]);

        $userId = $this->createUser();
        $merchant = User::find($userId);

        // Amount 50 is below channel's min_amount of 100
        $context = $this->makeContext(['amount' => '50.00']);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetUserChannel($context, $merchant, $channel);
    }

    public function test_validateAndGetUserChannel_returns_user_channel_when_valid(): void
    {
        $channelCode = $this->createChannel(['code' => 'UCOK_CH', 'real_name_enable' => false]);
        $channel = Channel::find($channelCode);

        $channelGroupId = $this->createChannelGroup($channelCode);
        $this->createChannelAmount($channelGroupId, $channelCode, [
            'min_amount' => '10.00',
            'max_amount' => '500.00',
        ]);

        $userId = $this->createUser();
        $this->createUserChannel($userId, $channelGroupId);
        $merchant = User::find($userId);

        $context = $this->makeContext(['amount' => '100.00']);

        [$userChannel, $channelAmount] = $this->service->validateAndGetUserChannel($context, $merchant, $channel);

        $this->assertInstanceOf(UserChannel::class, $userChannel);
        $this->assertInstanceOf(ChannelAmount::class, $channelAmount);
    }

    public function test_validateAndGetUserChannel_throws_when_no_suitable_user_channel(): void
    {
        $channelCode = $this->createChannel(['code' => 'UCNO_CH']);
        $channel = Channel::find($channelCode);

        $channelGroupId = $this->createChannelGroup($channelCode);
        $this->createChannelAmount($channelGroupId, $channelCode, [
            'min_amount' => '10.00',
            'max_amount' => '500.00',
        ]);

        // Merchant with no user channels
        $userId = $this->createUser();
        $merchant = User::find($userId);

        $context = $this->makeContext(['amount' => '100.00']);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetUserChannel($context, $merchant, $channel);
    }

    public function test_validateAndGetUserChannel_throws_when_below_user_channel_min_amount(): void
    {
        $channelCode = $this->createChannel(['code' => 'UCMIN_CH', 'real_name_enable' => false]);
        $channel = Channel::find($channelCode);

        $channelGroupId = $this->createChannelGroup($channelCode);
        $this->createChannelAmount($channelGroupId, $channelCode, [
            'min_amount' => '10.00',
            'max_amount' => '1000.00',
        ]);

        $userId = $this->createUser();
        // UserChannel with min_amount = 200
        $this->createUserChannel($userId, $channelGroupId, [
            'min_amount' => '200.00',
            'max_amount' => null,
        ]);
        $merchant = User::find($userId);

        // Amount 50 passes channel range (10-1000) but below user channel min (200)
        $context = $this->makeContext(['amount' => '50.00']);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetUserChannel($context, $merchant, $channel);
    }

    public function test_validateAndGetUserChannel_throws_when_above_user_channel_max_amount(): void
    {
        $channelCode = $this->createChannel(['code' => 'UCMAX_CH', 'real_name_enable' => false]);
        $channel = Channel::find($channelCode);

        $channelGroupId = $this->createChannelGroup($channelCode);
        $this->createChannelAmount($channelGroupId, $channelCode, [
            'min_amount' => '10.00',
            'max_amount' => '1000.00',
        ]);

        $userId = $this->createUser();
        // UserChannel with max_amount = 300
        $this->createUserChannel($userId, $channelGroupId, [
            'min_amount' => null,
            'max_amount' => '300.00',
        ]);
        $merchant = User::find($userId);

        // Amount 500 passes channel range (10-1000) but above user channel max (300)
        $context = $this->makeContext(['amount' => '500.00']);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetUserChannel($context, $merchant, $channel);
    }

    public function test_validateAndGetUserChannel_throws_when_real_name_required_but_missing(): void
    {
        $channelCode = $this->createChannel(['code' => 'UCRN_CH', 'real_name_enable' => true]);
        $channel = Channel::find($channelCode);

        $channelGroupId = $this->createChannelGroup($channelCode);
        $this->createChannelAmount($channelGroupId, $channelCode, [
            'min_amount' => '10.00',
            'max_amount' => '500.00',
        ]);

        $userId = $this->createUser();
        $this->createUserChannel($userId, $channelGroupId, [
            'real_name_enable' => true,
        ]);
        $merchant = User::find($userId);

        // No realName provided
        $context = $this->makeContext(['amount' => '100.00', 'realName' => null]);

        $this->expectException(TransactionValidationException::class);
        $this->service->validateAndGetUserChannel($context, $merchant, $channel);
    }

    public function test_validateAndGetUserChannel_passes_when_real_name_provided(): void
    {
        $channelCode = $this->createChannel(['code' => 'UCRNOK_CH', 'real_name_enable' => true]);
        $channel = Channel::find($channelCode);

        $channelGroupId = $this->createChannelGroup($channelCode);
        $this->createChannelAmount($channelGroupId, $channelCode, [
            'min_amount' => '10.00',
            'max_amount' => '500.00',
        ]);

        $userId = $this->createUser();
        $this->createUserChannel($userId, $channelGroupId, [
            'real_name_enable' => true,
        ]);
        $merchant = User::find($userId);

        $context = $this->makeContext(['amount' => '100.00', 'realName' => '王五']);

        [$userChannel, $channelAmount] = $this->service->validateAndGetUserChannel($context, $merchant, $channel);

        $this->assertInstanceOf(UserChannel::class, $userChannel);
        $this->assertInstanceOf(ChannelAmount::class, $channelAmount);
    }

}

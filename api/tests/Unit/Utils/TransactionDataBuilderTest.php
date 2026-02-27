<?php

namespace Tests\Unit\Utils;

use App\DTOs\TransactionParams;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Models\Wallet;
use App\Utils\BankCardTransferObject;
use App\Utils\TransactionDataBuilder;
use Illuminate\Http\Request;
use Tests\TestCase;

class TransactionDataBuilderTest extends TestCase
{
    private TransactionDataBuilder $builder;
    private BankCardTransferObject $bankCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new TransactionDataBuilder();
        $this->bankCard = (new BankCardTransferObject())->plain('工商银行', '6222000000000001', '张三', '广东省', '深圳市');
    }

    private function makeParams(array $overrides = []): TransactionParams
    {
        $defaults = [
            'amount' => '1000.00',
            'bankCard' => $this->bankCard,
            'clientIpv4' => '127.0.0.1',
            'orderNumber' => 'ORD123456',
            'note' => 'test note',
            'notifyUrl' => 'https://example.com/notify',
            'usdtRate' => '7.25',
            'toData' => [],
        ];

        return new TransactionParams(...array_merge($defaults, $overrides));
    }

    private function mockUser(array $attrs = []): User
    {
        $walletId = $attrs['wallet_id'] ?? 100;
        $wallet = new class($walletId) {
            private $id;
            public function __construct($id) { $this->id = $id; }
            public function getKey() { return $this->id; }
        };

        $userId = $attrs['id'] ?? 1;
        $user = new User();
        $user->id = $userId;
        $walletModel = new Wallet();
        $walletModel->id = $walletId;
        $user->setRelation('wallet', $walletModel);
        $user->account_mode = $attrs['account_mode'] ?? User::ACCOUNT_MODE_GENERAL;
        $user->withdraw_review_enable = $attrs['withdraw_review_enable'] ?? false;

        return $user;
    }

    // ── normalDeposit ───────────────────────────────────────────

    public function test_buildNormalDeposit_returns_correct_fields(): void
    {
        $provider = $this->mockUser(['id' => 5, 'wallet_id' => 50]);
        $params = $this->makeParams();

        $data = $this->builder->buildNormalDeposit($params, $provider);

        $this->assertSame(0, $data['from_id']);
        $this->assertSame(5, $data['to_id']);
        $this->assertSame(50, $data['to_wallet_id']);
        $this->assertSame(Transaction::TYPE_NORMAL_DEPOSIT, $data['type']);
        $this->assertSame(Transaction::STATUS_PAYING, $data['status']);
        $this->assertSame(Transaction::NOTIFY_STATUS_NONE, $data['notify_status']);
        $this->assertSame('1000.00', $data['amount']);
        $this->assertSame('1000.00', $data['floating_amount']);
        $this->assertSame(0, $data['actual_amount']);
        $this->assertSame('ORD123456', $data['order_number']);
        $this->assertSame('7.25', $data['usdt_rate']);
        $this->assertSame('127.0.0.1', $data['client_ipv4']);
        $this->assertSame('test note', $data['note']);
        $this->assertSame('https://example.com/notify', $data['notify_url']);
        $this->assertNull($data['from_account_mode']);
        $this->assertSame(User::ACCOUNT_MODE_GENERAL, $data['to_account_mode']);
    }

    public function test_buildNormalDeposit_usdt_rate_defaults_to_zero(): void
    {
        $provider = $this->mockUser();
        $params = $this->makeParams(['usdtRate' => null]);

        $data = $this->builder->buildNormalDeposit($params, $provider);

        $this->assertSame(0, $data['usdt_rate']);
    }

    // ── normalWithdraw ──────────────────────────────────────────

    public function test_buildNormalWithdraw_without_parent_review_disabled_returns_paying(): void
    {
        $user = $this->mockUser(['withdraw_review_enable' => false]);
        $params = $this->makeParams();

        $data = $this->builder->buildNormalWithdraw($params, $user);

        $this->assertSame(Transaction::STATUS_PAYING, $data['status']);
        $this->assertSame(Transaction::TYPE_NORMAL_WITHDRAW, $data['type']);
    }

    public function test_buildNormalWithdraw_without_parent_review_enabled_returns_pending_review(): void
    {
        $user = $this->mockUser(['withdraw_review_enable' => true]);
        $params = $this->makeParams();

        $data = $this->builder->buildNormalWithdraw($params, $user);

        $this->assertSame(Transaction::STATUS_PENDING_REVIEW, $data['status']);
    }

    public function test_buildNormalWithdraw_with_parent_returns_paying_regardless_of_review(): void
    {
        $user = $this->mockUser(['withdraw_review_enable' => true]);
        $parentTx = $this->createMock(Transaction::class);
        $parentTx->method('getKey')->willReturn(999);
        $params = $this->makeParams(['parent' => $parentTx]);

        $data = $this->builder->buildNormalWithdraw($params, $user);

        $this->assertSame(Transaction::STATUS_PAYING, $data['status']);
        $this->assertSame(999, $data['parent_id']);
    }

    public function test_buildNormalWithdraw_maps_user_fields(): void
    {
        $user = $this->mockUser(['id' => 10, 'wallet_id' => 200]);
        $params = $this->makeParams(['subType' => 'usdt']);

        $data = $this->builder->buildNormalWithdraw($params, $user);

        $this->assertSame(10, $data['from_id']);
        $this->assertSame(200, $data['from_wallet_id']);
        $this->assertSame(0, $data['to_id']);
        $this->assertSame('usdt', $data['sub_type']);
        $this->assertSame(User::ACCOUNT_MODE_GENERAL, $data['from_account_mode']);
        $this->assertNull($data['to_account_mode']);
    }

    // ── paufenWithdraw ──────────────────────────────────────────

    public function test_buildPaufenWithdraw_without_parent_review_disabled_returns_matching(): void
    {
        $merchant = $this->mockUser(['withdraw_review_enable' => false]);
        $params = $this->makeParams();

        $data = $this->builder->buildPaufenWithdraw($params, $merchant);

        $this->assertSame(Transaction::STATUS_MATCHING, $data['status']);
        $this->assertSame(Transaction::TYPE_PAUFEN_WITHDRAW, $data['type']);
    }

    public function test_buildPaufenWithdraw_without_parent_review_enabled_returns_pending_review(): void
    {
        $merchant = $this->mockUser(['withdraw_review_enable' => true]);
        $params = $this->makeParams();

        $data = $this->builder->buildPaufenWithdraw($params, $merchant);

        $this->assertSame(Transaction::STATUS_PENDING_REVIEW, $data['status']);
    }

    public function test_buildPaufenWithdraw_with_parent_returns_matching_regardless_of_review(): void
    {
        $merchant = $this->mockUser(['withdraw_review_enable' => true]);
        $parentTx = $this->createMock(Transaction::class);
        $parentTx->method('getKey')->willReturn(888);
        $params = $this->makeParams(['parent' => $parentTx]);

        $data = $this->builder->buildPaufenWithdraw($params, $merchant);

        $this->assertSame(Transaction::STATUS_MATCHING, $data['status']);
        $this->assertSame(888, $data['parent_id']);
    }

    public function test_buildPaufenWithdraw_to_id_is_null(): void
    {
        $merchant = $this->mockUser();
        $params = $this->makeParams();

        $data = $this->builder->buildPaufenWithdraw($params, $merchant);

        $this->assertNull($data['to_id']);
    }

    // ── thirdchannelWithdraw ────────────────────────────────────

    public function test_buildThirdchannelWithdraw_always_third_paying(): void
    {
        $user = $this->mockUser();
        $params = $this->makeParams();

        $data = $this->builder->buildThirdchannelWithdraw($params, $user);

        $this->assertSame(Transaction::STATUS_THIRD_PAYING, $data['status']);
        $this->assertSame(Transaction::TYPE_NORMAL_WITHDRAW, $data['type']);
    }

    public function test_buildThirdchannelWithdraw_includes_thirdchannel_id(): void
    {
        $user = $this->mockUser();
        $params = $this->makeParams();

        $data = $this->builder->buildThirdchannelWithdraw($params, $user, 42);

        $this->assertSame(42, $data['thirdchannel_id']);
    }

    public function test_buildThirdchannelWithdraw_thirdchannel_id_defaults_to_null(): void
    {
        $user = $this->mockUser();
        $params = $this->makeParams();

        $data = $this->builder->buildThirdchannelWithdraw($params, $user);

        $this->assertNull($data['thirdchannel_id']);
    }

    // ── internalTransfer ────────────────────────────────────────

    public function test_buildInternalTransfer_without_account_returns_matching(): void
    {
        $params = $this->makeParams();

        $data = $this->builder->buildInternalTransfer($params);

        $this->assertSame(Transaction::STATUS_MATCHING, $data['status']);
        $this->assertSame(Transaction::TYPE_INTERNAL_TRANSFER, $data['type']);
        $this->assertSame(0, $data['from_id']);
        $this->assertSame(0, $data['to_id']);
        $this->assertNull($data['to_channel_account_id']);
        $this->assertSame([], $data['to_channel_account']);
        $this->assertArrayNotHasKey('matched_at', $data);
    }

    public function test_buildInternalTransfer_with_account_returns_paying_and_matched(): void
    {
        $account = new UserChannelAccount();
        $account->user_id = 77;
        $account->id = 33;
        $account->detail = ['bank_name' => '招商银行'];
        $account->channel_code = 'bank_card';

        $params = $this->makeParams();

        $data = $this->builder->buildInternalTransfer($params, $account);

        $this->assertSame(Transaction::STATUS_PAYING, $data['status']);
        $this->assertSame(77, $data['to_id']);
        $this->assertSame(33, $data['to_channel_account_id']);
        $this->assertSame('bank_card', $data['to_channel_account']['channel_code']);
        $this->assertSame('招商银行', $data['to_channel_account']['bank_name']);
        $this->assertNotNull($data['matched_at']);
    }

    public function test_buildInternalTransfer_uses_bankCard_without_address(): void
    {
        $params = $this->makeParams();

        $data = $this->builder->buildInternalTransfer($params);

        $fromAccount = $data['from_channel_account'];
        $this->assertArrayHasKey(UserChannelAccount::DETAIL_KEY_BANK_NAME, $fromAccount);
        $this->assertArrayNotHasKey(UserChannelAccount::DETAIL_KEY_BANK_PROVINCE, $fromAccount);
        $this->assertArrayNotHasKey(UserChannelAccount::DETAIL_KEY_BANK_CITY, $fromAccount);
    }

    // ── paufenTransaction ───────────────────────────────────────

    public function test_buildPaufenTransaction_uses_floatingAmount_when_present(): void
    {
        $merchant = $this->mockUser(['id' => 20, 'wallet_id' => 300]);
        $channel = $this->createMock(Channel::class);
        $channel->method('getKey')->willReturn('bank_card');

        $params = $this->makeParams([
            'amount' => '500.00',
            'floatingAmount' => '499.50',
            'realName' => '王五',
            'binanceUsdtRate' => '7.20',
        ]);

        // Mock request for json_encode(request()->all())
        $this->app->instance('request', new Request(['foo' => 'bar']));

        $data = $this->builder->buildPaufenTransaction($params, $merchant, $channel);

        $this->assertSame(Transaction::TYPE_PAUFEN_TRANSACTION, $data['type']);
        $this->assertSame(Transaction::STATUS_MATCHING, $data['status']);
        $this->assertSame('500.00', $data['amount']);
        $this->assertSame('499.50', $data['floating_amount']);
        $this->assertSame(20, $data['to_id']);
        $this->assertSame(300, $data['to_wallet_id']);
        $this->assertSame('bank_card', $data['channel_code']);
        $this->assertNull($data['from_id']);
        $this->assertSame([], $data['from_channel_account']);
    }

    public function test_buildPaufenTransaction_falls_back_to_amount_when_no_floatingAmount(): void
    {
        $merchant = $this->mockUser();
        $channel = $this->createMock(Channel::class);
        $channel->method('getKey')->willReturn('bank_card');
        $params = $this->makeParams(['amount' => '800.00', 'floatingAmount' => null]);

        $this->app->instance('request', new Request());

        $data = $this->builder->buildPaufenTransaction($params, $merchant, $channel);

        $this->assertSame('800.00', $data['floating_amount']);
        $this->assertSame('800.00', $data['amount']);
    }

    public function test_buildPaufenTransaction_to_channel_account_contains_real_name_and_query(): void
    {
        $merchant = $this->mockUser();
        $channel = $this->createMock(Channel::class);
        $channel->method('getKey')->willReturn('bank_card');
        $params = $this->makeParams([
            'realName' => '赵六',
            'binanceUsdtRate' => '7.30',
            'toData' => ['extra_key' => 'extra_value'],
        ]);

        $this->app->instance('request', new Request(['q' => '1']));

        $data = $this->builder->buildPaufenTransaction($params, $merchant, $channel);

        $to = $data['to_channel_account'];
        $this->assertSame('赵六', $to[UserChannelAccount::DETAIL_KEY_REAL_NAME]);
        $this->assertSame('7.30', $to['binance_usdt_rate']);
        $this->assertSame('extra_value', $to['extra_key']);
        $this->assertSame(json_encode(['q' => '1']), $to['query']);
    }

    // ── common field mapping ────────────────────────────────────

    public function test_from_channel_account_uses_bankCard_toFromChannelAccount(): void
    {
        $provider = $this->mockUser();
        $params = $this->makeParams();

        $data = $this->builder->buildNormalDeposit($params, $provider);

        $fromAccount = $data['from_channel_account'];
        $this->assertSame('工商银行', $fromAccount[UserChannelAccount::DETAIL_KEY_BANK_NAME]);
        $this->assertSame('6222000000000001', $fromAccount[UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER]);
        $this->assertSame('张三', $fromAccount[UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME]);
    }

    public function test_to_channel_account_uses_toData_when_present(): void
    {
        $provider = $this->mockUser();
        $params = $this->makeParams(['toData' => ['key' => 'value']]);

        $data = $this->builder->buildNormalDeposit($params, $provider);

        $this->assertSame(['key' => 'value'], $data['to_channel_account']);
    }

    public function test_to_channel_account_defaults_to_empty_array(): void
    {
        $provider = $this->mockUser();
        $params = $this->makeParams(['toData' => []]);

        $data = $this->builder->buildNormalDeposit($params, $provider);

        $this->assertSame([], $data['to_channel_account']);
    }
}

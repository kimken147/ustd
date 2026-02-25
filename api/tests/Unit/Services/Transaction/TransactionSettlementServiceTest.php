<?php

namespace Tests\Unit\Services\Transaction;

use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\User;
use App\Models\Wallet;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\TransactionSettlementService;
use App\Utils\BCMathUtil;
use App\Utils\UserChannelAccountUtil;
use App\Utils\WalletUtil;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class TransactionSettlementServiceTest extends TestCase
{
    private TransactionSettlementService $service;
    private WalletUtil $walletUtil;
    private BCMathUtil $bcMath;
    private FeatureToggleRepository $featureToggleRepo;
    private UserChannelAccountUtil $userChannelAccountUtil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->walletUtil = Mockery::mock(WalletUtil::class);
        $this->bcMath = new BCMathUtil();
        $this->featureToggleRepo = Mockery::mock(FeatureToggleRepository::class);
        $this->userChannelAccountUtil = Mockery::mock(UserChannelAccountUtil::class);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::CANCEL_PAUFEN_MECHANISM)
            ->andReturn(false)
            ->byDefault();

        $this->service = new TransactionSettlementService(
            $this->walletUtil,
            $this->bcMath,
            $this->featureToggleRepo,
            $this->userChannelAccountUtil
        );
    }

    // --- settleProfit ---

    public function test_settleProfit_skips_zero_profit(): void
    {
        $fee = Mockery::mock(TransactionFee::class)->makePartial();
        $fee->shouldReceive('getAttribute')->with('user_id')->andReturn(2);
        $fee->shouldReceive('getAttribute')->with('actual_profit')->andReturn('0.00');

        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->shouldReceive('getAttribute')->with('to_id')->andReturn(1);
        $transaction->shouldReceive('getAttribute')->with('transactionFees')->andReturn(new Collection([$fee]));

        $this->walletUtil->shouldNotReceive('deposit');

        $this->service->settleProfit($transaction);
        $this->assertTrue(true);
    }

    public function test_settleProfit_skips_credit_mode(): void
    {
        $fee = Mockery::mock(TransactionFee::class)->makePartial();
        $fee->shouldReceive('getAttribute')->with('user_id')->andReturn(2);
        $fee->shouldReceive('getAttribute')->with('actual_profit')->andReturn('10.00');
        $fee->shouldReceive('creditModeEnabled')->andReturn(true);

        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->shouldReceive('getAttribute')->with('to_id')->andReturn(1);
        $transaction->shouldReceive('getAttribute')->with('transactionFees')->andReturn(new Collection([$fee]));
        $transaction->shouldReceive('isPaufenTransaction')->andReturn(true);

        $this->walletUtil->shouldNotReceive('deposit');

        $this->service->settleProfit($transaction);
        $this->assertTrue(true);
    }

    public function test_settleProfit_deposits_for_provider(): void
    {
        $providerWallet = Mockery::mock(Wallet::class);

        $providerUser = Mockery::mock(User::class)->makePartial();
        $providerUser->shouldReceive('getAttribute')->with('wallet')->andReturn($providerWallet);
        $providerUser->shouldReceive('getAttribute')->with('role')->andReturn(User::ROLE_PROVIDER);

        $fee = Mockery::mock(TransactionFee::class)->makePartial();
        $fee->shouldReceive('getAttribute')->with('user_id')->andReturn(2);
        $fee->shouldReceive('getAttribute')->with('actual_profit')->andReturn('5.00');
        $fee->shouldReceive('creditModeEnabled')->andReturn(false);
        $fee->shouldReceive('depositModeEnabled')->andReturn(false);
        $fee->shouldReceive('getAttribute')->with('user')->andReturn($providerUser);

        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->shouldReceive('getAttribute')->with('to_id')->andReturn(1);
        $transaction->shouldReceive('getAttribute')->with('transactionFees')->andReturn(new Collection([$fee]));
        $transaction->shouldReceive('isPaufenTransaction')->andReturn(true);
        $transaction->shouldReceive('getAttribute')->with('system_order_number')->andReturn('SYS001');

        $this->walletUtil->shouldReceive('deposit')
            ->once()
            ->with($providerWallet, '0.00', '5.00', 'SYS001');

        $this->service->settleProfit($transaction);
    }

    public function test_settleProfit_deposits_for_merchant_uses_balance(): void
    {
        $merchantWallet = Mockery::mock(Wallet::class);

        $merchantUser = Mockery::mock(User::class)->makePartial();
        $merchantUser->shouldReceive('getAttribute')->with('wallet')->andReturn($merchantWallet);
        $merchantUser->shouldReceive('getAttribute')->with('role')->andReturn(User::ROLE_MERCHANT);

        $fee = Mockery::mock(TransactionFee::class)->makePartial();
        $fee->shouldReceive('getAttribute')->with('user_id')->andReturn(2);
        $fee->shouldReceive('getAttribute')->with('actual_profit')->andReturn('8.00');
        $fee->shouldReceive('creditModeEnabled')->andReturn(false);
        $fee->shouldReceive('depositModeEnabled')->andReturn(false);
        $fee->shouldReceive('getAttribute')->with('user')->andReturn($merchantUser);

        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->shouldReceive('getAttribute')->with('to_id')->andReturn(1);
        $transaction->shouldReceive('getAttribute')->with('transactionFees')->andReturn(new Collection([$fee]));
        $transaction->shouldReceive('isPaufenTransaction')->andReturn(true);
        $transaction->shouldReceive('getAttribute')->with('order_number')->andReturn('ORD001');

        // merchant: profit goes to balance (amount), not profit
        $this->walletUtil->shouldReceive('deposit')
            ->once()
            ->with($merchantWallet, '8.00', '0.00', 'ORD001');

        $this->service->settleProfit($transaction);
    }

    public function test_settleProfit_deposit_mode_routes_to_fromWallet(): void
    {
        $fromWallet = Mockery::mock(Wallet::class);

        $providerUser = Mockery::mock(User::class)->makePartial();
        $providerUser->shouldReceive('getAttribute')->with('wallet')->andReturn(Mockery::mock(Wallet::class));
        $providerUser->shouldReceive('getAttribute')->with('role')->andReturn(User::ROLE_PROVIDER);

        $fee = Mockery::mock(TransactionFee::class)->makePartial();
        $fee->shouldReceive('getAttribute')->with('user_id')->andReturn(2);
        $fee->shouldReceive('getAttribute')->with('actual_profit')->andReturn('3.00');
        $fee->shouldReceive('creditModeEnabled')->andReturn(false);
        $fee->shouldReceive('depositModeEnabled')->andReturn(true);
        $fee->shouldReceive('getAttribute')->with('user')->andReturn($providerUser);

        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->shouldReceive('getAttribute')->with('to_id')->andReturn(1);
        $transaction->shouldReceive('getAttribute')->with('transactionFees')->andReturn(new Collection([$fee]));
        $transaction->shouldReceive('isPaufenTransaction')->andReturn(true);
        $transaction->shouldReceive('getAttribute')->with('fromWallet')->andReturn($fromWallet);
        $transaction->shouldReceive('getAttribute')->with('system_order_number')->andReturn('SYS001');

        // deposit mode routes to fromWallet instead of provider's wallet
        $this->walletUtil->shouldReceive('deposit')
            ->once()
            ->with($fromWallet, '0.00', '3.00', 'SYS001');

        $this->service->settleProfit($transaction);
    }

    // --- rollbackProfit ---

    public function test_rollbackProfit_calls_wallet_withdraw(): void
    {
        $providerWallet = Mockery::mock(Wallet::class);

        $providerUser = Mockery::mock(User::class)->makePartial();
        $providerUser->shouldReceive('getAttribute')->with('wallet')->andReturn($providerWallet);
        $providerUser->shouldReceive('getAttribute')->with('role')->andReturn(User::ROLE_PROVIDER);

        $fee = Mockery::mock(TransactionFee::class)->makePartial();
        $fee->shouldReceive('getAttribute')->with('user_id')->andReturn(2);
        $fee->shouldReceive('getAttribute')->with('actual_profit')->andReturn('5.00');
        $fee->shouldReceive('creditModeEnabled')->andReturn(false);
        $fee->shouldReceive('depositModeEnabled')->andReturn(false);
        $fee->shouldReceive('getAttribute')->with('user')->andReturn($providerUser);

        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->shouldReceive('getAttribute')->with('to_id')->andReturn(1);
        $transaction->shouldReceive('getAttribute')->with('transactionFees')->andReturn(new Collection([$fee]));
        $transaction->shouldReceive('isPaufenTransaction')->andReturn(false);
        $transaction->shouldReceive('getAttribute')->with('order_number')->andReturn('ORD001');

        $this->walletUtil->shouldReceive('withdraw')
            ->once()
            ->with($providerWallet, '5.00', 'ORD001', 'transaction');

        $this->service->rollbackProfit($transaction);
    }

    // --- rollbackPaufenSettlement skips non-success origin ---

    public function test_rollbackPaufenSettlement_skips_non_success_origin(): void
    {
        $transaction = Mockery::mock(Transaction::class)->makePartial();

        $this->walletUtil->shouldNotReceive('depositRollback');

        $this->service->rollbackPaufenSettlement($transaction, Transaction::STATUS_PAYING);
        $this->assertTrue(true);
    }

    // --- rollbackWithdrawSettlement skips non-success origin ---

    public function test_rollbackWithdrawSettlement_skips_non_success_origin(): void
    {
        $transaction = Mockery::mock(Transaction::class)->makePartial();

        $this->walletUtil->shouldNotReceive('depositRollback');

        $this->service->rollbackWithdrawSettlement($transaction, Transaction::STATUS_PAYING);
        $this->assertTrue(true);
    }
}

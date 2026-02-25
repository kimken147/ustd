<?php

namespace Tests\Unit\Services\Transaction;

use App\Exceptions\ChildWithdrawCannotSeparateException;
use App\Exceptions\InvalidChildWithdrawAmountException;
use App\Exceptions\InvalidMaxSeparateWithdrawCountException;
use App\Exceptions\InvalidMinSeparateWithdrawCountException;
use App\Exceptions\OnlyMerchantCanSeparateWithdrawException;
use App\Exceptions\SeparateWithdrawTotalAmountNotMatchException;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionSeparationService;
use App\Utils\BCMathUtil;
use App\Utils\BankCardTransferObject;
use App\Utils\TransactionFactory;
use App\Utils\TransactionMutator;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class TransactionSeparationServiceTest extends TestCase
{
    private TransactionSeparationService $service;
    private BCMathUtil $bcMath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bcMath = new BCMathUtil();
        $transactionFactory = Mockery::mock(TransactionFactory::class);
        $transactionMutator = Mockery::mock(TransactionMutator::class);
        $bankCardTransferObject = Mockery::mock(BankCardTransferObject::class);

        $this->service = new TransactionSeparationService(
            $this->bcMath,
            $transactionFactory,
            $transactionMutator,
            $bankCardTransferObject
        );
    }

    private function makeWithdrawTransaction(array $overrides = []): Transaction
    {
        $from = Mockery::mock(User::class)->makePartial();
        $from->shouldReceive('getAttribute')->with('role')->andReturn($overrides['from_role'] ?? User::ROLE_MERCHANT);

        $transaction = Mockery::mock(Transaction::class)->makePartial();
        $transaction->shouldReceive('getAttribute')->with('type')->andReturn($overrides['type'] ?? Transaction::TYPE_NORMAL_WITHDRAW);
        $transaction->shouldReceive('getAttribute')->with('to_id')->andReturn($overrides['to_id'] ?? null);
        $transaction->shouldReceive('getAttribute')->with('amount')->andReturn($overrides['amount'] ?? '1000.00');
        $transaction->shouldReceive('getAttribute')->with('from')->andReturn($from);
        $transaction->shouldReceive('isChild')->andReturn($overrides['isChild'] ?? false);

        return $transaction;
    }

    public function test_separateWithdraw_child_less_than_2_throws(): void
    {
        $transaction = $this->makeWithdrawTransaction();
        $childWithdraws = new Collection([
            ['amount' => '1000.00'],
        ]);

        $this->expectException(InvalidMinSeparateWithdrawCountException::class);
        $this->service->separateWithdraw($transaction, $childWithdraws, false, fn () => null);
    }

    public function test_separateWithdraw_child_more_than_10_throws(): void
    {
        $children = [];
        for ($i = 0; $i < 11; $i++) {
            $children[] = ['amount' => '100.00'];
        }

        $transaction = $this->makeWithdrawTransaction(['amount' => '1100.00']);
        $childWithdraws = new Collection($children);

        $this->expectException(InvalidMaxSeparateWithdrawCountException::class);
        $this->service->separateWithdraw($transaction, $childWithdraws, false, fn () => null);
    }

    public function test_separateWithdraw_amount_mismatch_throws(): void
    {
        $transaction = $this->makeWithdrawTransaction(['amount' => '1000.00']);
        $childWithdraws = new Collection([
            ['amount' => '400.00'],
            ['amount' => '500.00'],
        ]);

        $this->expectException(SeparateWithdrawTotalAmountNotMatchException::class);
        $this->service->separateWithdraw($transaction, $childWithdraws, false, fn () => null);
    }

    public function test_separateWithdraw_zero_amount_throws(): void
    {
        $transaction = $this->makeWithdrawTransaction(['amount' => '1000.00']);
        $childWithdraws = new Collection([
            ['amount' => '0.00'],
            ['amount' => '1000.00'],
        ]);

        $this->expectException(InvalidChildWithdrawAmountException::class);
        $this->service->separateWithdraw($transaction, $childWithdraws, false, fn () => null);
    }

    public function test_separateWithdraw_non_merchant_throws(): void
    {
        $transaction = $this->makeWithdrawTransaction(['from_role' => User::ROLE_PROVIDER]);
        $childWithdraws = new Collection([
            ['amount' => '500.00'],
            ['amount' => '500.00'],
        ]);

        $this->expectException(OnlyMerchantCanSeparateWithdrawException::class);
        $this->service->separateWithdraw($transaction, $childWithdraws, false, fn () => null);
    }

    public function test_separateWithdraw_child_cannot_reseparate_throws(): void
    {
        $transaction = $this->makeWithdrawTransaction(['isChild' => true]);
        $childWithdraws = new Collection([
            ['amount' => '500.00'],
            ['amount' => '500.00'],
        ]);

        $this->expectException(ChildWithdrawCannotSeparateException::class);
        $this->service->separateWithdraw($transaction, $childWithdraws, false, fn () => null);
    }
}

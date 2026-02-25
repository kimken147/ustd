<?php

namespace Tests\Unit\Services\Transaction;

use App\Exceptions\DifferentChildWithdrawStatusException;
use App\Exceptions\PaufenTransactionHasBeenLockedException;
use App\Exceptions\SeparatedWithdrawShouldCompleteChildrenException;
use App\Exceptions\TransactionLockerNotYouException;
use App\Exceptions\TransactionShouldLockBeforeUpdateException;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionStateValidator;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TransactionStateValidatorTest extends TestCase
{
    private TransactionStateValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new TransactionStateValidator();
    }

    private function mockTransaction(array $attributes = [], array $methods = []): Transaction
    {
        $transaction = Mockery::mock(Transaction::class)->makePartial();
        foreach ($attributes as $key => $value) {
            $transaction->shouldReceive('getAttribute')->with($key)->andReturn($value);
            $transaction->shouldReceive('__get')->with($key)->andReturn($value);
        }
        foreach ($methods as $method => $return) {
            $transaction->shouldReceive($method)->andReturn($return);
        }
        return $transaction;
    }

    // --- validatePaufenLock ---

    public function test_validatePaufenLock_locked_by_different_user_throws(): void
    {
        $operator = Mockery::mock(User::class);

        $locker = Mockery::mock(User::class);
        $locker->shouldReceive('is')->with($operator)->andReturn(false);

        $transaction = $this->mockTransaction(
            ['locked' => true, 'lockedBy' => $locker],
            ['isPaufenTransaction' => true]
        );

        $this->expectException(PaufenTransactionHasBeenLockedException::class);
        $this->validator->validatePaufenLock($transaction, $operator);
    }

    public function test_validatePaufenLock_not_locked_passes(): void
    {
        $transaction = $this->mockTransaction(
            ['locked' => false],
            ['isPaufenTransaction' => true]
        );

        $this->validator->validatePaufenLock($transaction);
        $this->assertTrue(true);
    }

    public function test_validatePaufenLock_same_user_passes(): void
    {
        $operator = Mockery::mock(User::class);
        $locker = Mockery::mock(User::class);
        $locker->shouldReceive('is')->with($operator)->andReturn(true);

        $transaction = $this->mockTransaction(
            ['locked' => true, 'lockedBy' => $locker],
            ['isPaufenTransaction' => true]
        );

        $this->validator->validatePaufenLock($transaction, $operator);
        $this->assertTrue(true);
    }

    public function test_validatePaufenLock_non_paufen_skips(): void
    {
        $transaction = $this->mockTransaction(
            ['locked' => true],
            ['isPaufenTransaction' => false]
        );

        $this->validator->validatePaufenLock($transaction);
        $this->assertTrue(true);
    }

    // --- validateLockBeforeUpdate ---

    public function test_validateLockBeforeUpdate_admin_not_locked_throws(): void
    {
        $mainUser = Mockery::mock(User::class);
        $mainUser->shouldReceive('isAdmin')->andReturn(true);

        $operator = Mockery::mock(User::class);
        $operator->shouldReceive('mainUser')->andReturn($mainUser);

        $transaction = $this->mockTransaction(
            ['type' => Transaction::TYPE_PAUFEN_TRANSACTION, 'locked' => false]
        );

        $this->expectException(TransactionShouldLockBeforeUpdateException::class);
        $this->validator->validateLockBeforeUpdate($transaction, $operator);
    }

    public function test_validateLockBeforeUpdate_withdraw_not_locked_throws(): void
    {
        $operator = Mockery::mock(User::class);

        $transaction = $this->mockTransaction(
            ['type' => Transaction::TYPE_NORMAL_WITHDRAW, 'locked' => false]
        );

        $this->expectException(TransactionShouldLockBeforeUpdateException::class);
        $this->validator->validateLockBeforeUpdate($transaction, $operator);
    }

    public function test_validateLockBeforeUpdate_wrong_locker_throws(): void
    {
        $operator = Mockery::mock(User::class);

        $locker = Mockery::mock(User::class);
        $locker->shouldReceive('is')->with($operator)->andReturn(false);

        $transaction = $this->mockTransaction(
            ['type' => Transaction::TYPE_PAUFEN_WITHDRAW, 'locked' => true, 'lockedBy' => $locker]
        );

        $this->expectException(TransactionLockerNotYouException::class);
        $this->validator->validateLockBeforeUpdate($transaction, $operator);
    }

    public function test_validateLockBeforeUpdate_provider_on_paufen_passes(): void
    {
        $mainUser = Mockery::mock(User::class);
        $mainUser->shouldReceive('isAdmin')->andReturn(false);

        $operator = Mockery::mock(User::class);
        $operator->shouldReceive('mainUser')->andReturn($mainUser);

        $transaction = $this->mockTransaction(
            ['type' => Transaction::TYPE_PAUFEN_TRANSACTION]
        );

        $this->validator->validateLockBeforeUpdate($transaction, $operator);
        $this->assertTrue(true);
    }

    // --- validateNotSeparatedParent ---

    public function test_validateNotSeparatedParent_has_children_throws(): void
    {
        $childrenRelation = Mockery::mock(HasMany::class);
        $childrenRelation->shouldReceive('exists')->andReturn(true);

        $transaction = $this->mockTransaction(
            [],
            ['isWithdraw' => true, 'children' => $childrenRelation]
        );

        $this->expectException(SeparatedWithdrawShouldCompleteChildrenException::class);
        $this->validator->validateNotSeparatedParent($transaction);
    }

    public function test_validateNotSeparatedParent_no_children_passes(): void
    {
        $childrenRelation = Mockery::mock(HasMany::class);
        $childrenRelation->shouldReceive('exists')->andReturn(false);

        $transaction = $this->mockTransaction(
            [],
            ['isWithdraw' => true, 'children' => $childrenRelation]
        );

        $this->validator->validateNotSeparatedParent($transaction);
        $this->assertTrue(true);
    }

    // --- validateChildCanBeSuccess / validateChildCanBeFailed ---
    // These methods use Transaction::where() static calls, which require database integration.
    // Testing them as integration tests with DatabaseTransactions.

    public function test_validateChildCanBeSuccess_non_withdraw_passes(): void
    {
        $transaction = $this->mockTransaction(
            [],
            ['isWithdraw' => false]
        );

        $this->validator->validateChildCanBeSuccess($transaction);
        $this->assertTrue(true);
    }

    public function test_validateChildCanBeSuccess_not_child_passes(): void
    {
        $transaction = $this->mockTransaction(
            [],
            ['isWithdraw' => true, 'isChild' => false]
        );

        $this->validator->validateChildCanBeSuccess($transaction);
        $this->assertTrue(true);
    }

    public function test_validateChildCanBeFailed_not_separated_child_passes(): void
    {
        $transaction = $this->mockTransaction(
            [],
            ['isWithdrawSeparatedChild' => false]
        );

        $this->validator->validateChildCanBeFailed($transaction);
        $this->assertTrue(true);
    }
}

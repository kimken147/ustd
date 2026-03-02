<?php

namespace Tests\Unit\Utils;

use App\Models\ChannelAmount;
use App\Models\ChannelGroup;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Utils\UserChannelUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UserChannelUtilTest extends TestCase
{
    use DatabaseTransactions;

    private UserChannelUtil $util;

    protected function setUp(): void
    {
        parent::setUp();
        $this->util = new UserChannelUtil();
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function createUser(array $overrides = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name'            => 'test_user',
            'username'        => 'test_' . uniqid(),
            'password'        => bcrypt('password'),
            'role'            => User::ROLE_MERCHANT,
            'status'          => User::STATUS_ENABLE,
            'account_mode'    => User::ACCOUNT_MODE_GENERAL,
            'secret_key'      => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'balance_limit'   => 0,
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $overrides));

        return User::find($id);
    }

    private function createChannel(string $code = null): string
    {
        $code = $code ?? 'CH' . strtoupper(uniqid());
        // Truncate to 20 chars (channel code is char(20))
        $code = substr($code, 0, 20);

        DB::table('channels')->insert([
            'code'                       => $code,
            'name'                       => 'TestChannel',
            'status'                     => 1,
            'order_timeout'              => 300,
            'order_timeout_enable'       => 1,
            'transaction_timeout'        => 300,
            'transaction_timeout_enable' => 1,
            'floating'                   => 0.01,
            'floating_enable'            => 0,
            'real_name_enable'           => 0,
            'note_enable'                => 0,
            'max_one_ignore_amount'      => 0,
            'present_result'             => 0,
            'country'                    => 'cn',
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ]);

        return $code;
    }

    private function createChannelGroup(string $channelCode = null, array $overrides = []): ChannelGroup
    {
        $channelCode = $channelCode ?? $this->createChannel();

        $id = DB::table('channel_groups')->insertGetId(array_merge([
            'channel_code' => $channelCode,
            'fixed_amount' => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ], $overrides));

        return ChannelGroup::find($id);
    }

    private function createChannelAmount(int $channelGroupId, string $channelCode, array $overrides = []): ChannelAmount
    {
        $id = DB::table('channel_amounts')->insertGetId(array_merge([
            'channel_group_id' => $channelGroupId,
            'channel_code'     => $channelCode,
            'min_amount'       => '10.00',
            'max_amount'       => '50000.00',
            'created_at'       => now(),
            'updated_at'       => now(),
        ], $overrides));

        return ChannelAmount::find($id);
    }

    private function createUserChannel(int $userId, int $channelGroupId, array $overrides = []): UserChannel
    {
        $id = DB::table('user_channels')->insertGetId(array_merge([
            'user_id'          => $userId,
            'channel_group_id' => $channelGroupId,
            'status'           => UserChannel::STATUS_ENABLED,
            'fee_percent'      => '1.00',
            'min_amount'       => '10.00',
            'max_amount'       => '50000.00',
            'created_at'       => now(),
            'updated_at'       => now(),
        ], $overrides));

        return UserChannel::find($id);
    }

    private function createWallet(int $userId, array $overrides = []): int
    {
        return DB::table('wallets')->insertGetId(array_merge([
            'user_id'        => $userId,
            'balance'        => '10000.00',
            'profit'         => '0.00',
            'frozen_balance' => '0.00',
            'withdraw_fee'   => '0.00',
            'status'         => 1,
            'created_at'     => now(),
            'updated_at'     => now(),
        ], $overrides));
    }

    /**
     * Create a UserChannelAccount linked to a ChannelAmount in the given ChannelGroup.
     */
    private function createUserChannelAccount(
        int $userId,
        int $walletId,
        int $channelAmountId,
        array $overrides = []
    ): UserChannelAccount {
        $id = DB::table('user_channel_accounts')->insertGetId(array_merge([
            'user_id'           => $userId,
            'channel_amount_id' => $channelAmountId,
            'wallet_id'         => $walletId,
            'bank_id'           => 0,
            'status'            => UserChannelAccount::STATUS_ENABLE,
            'type'              => UserChannelAccount::TYPE_DEPOSIT_WITHDRAW,
            'balance'           => '0.00',
            'fee_percent'       => '1.00',
            'min_amount'        => '10.00',
            'max_amount'        => '50000.00',
            'account'           => 'test_account_' . uniqid(),
            'detail'            => json_encode([]),
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $overrides));

        return UserChannelAccount::find($id);
    }

    /**
     * Create the full chain: Channel → ChannelGroup → ChannelAmount, and return all parts.
     */
    private function createChannelChain(): array
    {
        $channelCode  = $this->createChannel();
        $channelGroup = $this->createChannelGroup($channelCode);
        $channelAmount = $this->createChannelAmount($channelGroup->id, $channelCode);

        return compact('channelCode', 'channelGroup', 'channelAmount');
    }

    // ---------------------------------------------------------------
    //  disable()
    // ---------------------------------------------------------------

    public function test_disable_sets_user_channel_status_to_disabled(): void
    {
        $user = $this->createUser();
        $channelGroup = $this->createChannelGroup();
        $userChannel = $this->createUserChannel($user->id, $channelGroup->id, [
            'status' => UserChannel::STATUS_ENABLED,
        ]);

        $this->util->disable($userChannel);

        $userChannel->refresh();
        $this->assertSame(UserChannel::STATUS_DISABLED, $userChannel->status);
    }

    public function test_disable_downgrades_online_accounts_to_enabled(): void
    {
        $chain = $this->createChannelChain();
        $channelGroup = $chain['channelGroup'];
        $channelAmount = $chain['channelAmount'];

        $user = $this->createUser();
        $walletId = $this->createWallet($user->id);

        $userChannel = $this->createUserChannel($user->id, $channelGroup->id);

        // Create an ONLINE account linked to the same channel group via channelAmount
        $account = $this->createUserChannelAccount($user->id, $walletId, $channelAmount->id, [
            'status' => UserChannelAccount::STATUS_ONLINE,
        ]);

        $this->util->disable($userChannel);

        $account->refresh();
        $this->assertSame(UserChannelAccount::STATUS_ENABLE, (int) $account->status);
    }

    // ---------------------------------------------------------------
    //  enable()
    // ---------------------------------------------------------------

    public function test_enable_sets_user_channel_status_to_enabled(): void
    {
        $user = $this->createUser();
        $channelGroup = $this->createChannelGroup();
        $userChannel = $this->createUserChannel($user->id, $channelGroup->id, [
            'status'      => UserChannel::STATUS_DISABLED,
            'fee_percent' => '1.00',
        ]);

        $this->util->enable($userChannel);

        $userChannel->refresh();
        $this->assertSame(User::STATUS_ENABLE, $userChannel->status);
    }

    public function test_enable_aborts_when_fee_percent_is_null(): void
    {
        $user = $this->createUser();
        $channelGroup = $this->createChannelGroup();
        $userChannel = $this->createUserChannel($user->id, $channelGroup->id, [
            'status'      => UserChannel::STATUS_DISABLED,
            'fee_percent' => null,
        ]);

        $this->expectException(HttpException::class);

        $this->util->enable($userChannel);
    }

    // ---------------------------------------------------------------
    //  isInvalidFee()
    // ---------------------------------------------------------------

    public function test_isInvalidFee_returns_false_when_fee_is_valid_for_merchant(): void
    {
        // Parent merchant fee=1.00, target=1.50, no children
        // For ROLE_MERCHANT: parentFeeComparator='>' means invalid when parent.fee > target
        // parent.fee(1.00) <= target(1.50) → valid → returns false
        $parent = $this->createUser([
            '_lft'      => 1,
            '_rgt'      => 4,
            'parent_id' => null,
            'role'      => User::ROLE_MERCHANT,
        ]);
        $child = $this->createUser([
            '_lft'      => 2,
            '_rgt'      => 3,
            'parent_id' => $parent->getKey(),
            'role'      => User::ROLE_MERCHANT,
        ]);

        $channelGroup = $this->createChannelGroup();

        $this->createUserChannel($parent->id, $channelGroup->id, [
            'fee_percent' => '1.00',
        ]);
        $childUserChannel = $this->createUserChannel($child->id, $channelGroup->id, [
            'fee_percent' => '2.00',
        ]);

        // Target=1.50: parent(1.00) <= 1.50 OK, no descendants of child → valid
        $result = $this->util->isInvalidFee($childUserChannel, '1.50');

        $this->assertFalse($result);
    }

    public function test_isInvalidFee_returns_true_when_parent_fee_exceeds_merchant_target(): void
    {
        // For ROLE_MERCHANT: parentFeeComparator='>'
        // parent.fee(2.00) > target(1.50) → invalid → returns true
        $parent = $this->createUser([
            '_lft'      => 1,
            '_rgt'      => 4,
            'parent_id' => null,
            'role'      => User::ROLE_MERCHANT,
        ]);
        $child = $this->createUser([
            '_lft'      => 2,
            '_rgt'      => 3,
            'parent_id' => $parent->getKey(),
            'role'      => User::ROLE_MERCHANT,
        ]);

        $channelGroup = $this->createChannelGroup();

        $this->createUserChannel($parent->id, $channelGroup->id, [
            'fee_percent' => '2.00',
        ]);
        $childUserChannel = $this->createUserChannel($child->id, $channelGroup->id, [
            'fee_percent' => '1.50',
        ]);

        // Target=1.50: parent(2.00) > 1.50 → invalid
        $result = $this->util->isInvalidFee($childUserChannel, '1.50');

        $this->assertTrue($result);
    }

    public function test_isInvalidFee_returns_true_when_child_fee_below_merchant_target(): void
    {
        // For ROLE_MERCHANT: childFeeComparator='<'
        // child.fee(0.50) < target(1.00) → invalid → returns true
        $parent = $this->createUser([
            '_lft'      => 1,
            '_rgt'      => 6,
            'parent_id' => null,
            'role'      => User::ROLE_MERCHANT,
        ]);
        $child = $this->createUser([
            '_lft'      => 2,
            '_rgt'      => 5,
            'parent_id' => $parent->getKey(),
            'role'      => User::ROLE_MERCHANT,
        ]);
        $grandchild = $this->createUser([
            '_lft'      => 3,
            '_rgt'      => 4,
            'parent_id' => $child->getKey(),
            'role'      => User::ROLE_MERCHANT,
        ]);

        $channelGroup = $this->createChannelGroup();

        $this->createUserChannel($parent->id, $channelGroup->id, [
            'fee_percent' => '0.50',
        ]);
        $childUserChannel = $this->createUserChannel($child->id, $channelGroup->id, [
            'fee_percent' => '1.00',
        ]);
        $this->createUserChannel($grandchild->id, $channelGroup->id, [
            'fee_percent' => '0.50',
        ]);

        // Target=1.00 for child: grandchild.fee(0.50) < 1.00 → invalid
        $result = $this->util->isInvalidFee($childUserChannel, '1.00');

        $this->assertTrue($result);
    }

    // ---------------------------------------------------------------
    //  updateFee()
    // ---------------------------------------------------------------

    public function test_updateFee_updates_user_channel_and_accounts(): void
    {
        $chain = $this->createChannelChain();
        $channelGroup = $chain['channelGroup'];
        $channelAmount = $chain['channelAmount'];

        $user = $this->createUser();
        $walletId = $this->createWallet($user->id);

        $userChannel = $this->createUserChannel($user->id, $channelGroup->id, [
            'fee_percent' => '1.00',
        ]);
        $account = $this->createUserChannelAccount($user->id, $walletId, $channelAmount->id, [
            'fee_percent' => '1.00',
        ]);

        $this->util->updateFee($userChannel, '2.50');

        $userChannel->refresh();
        $account->refresh();

        $this->assertEquals('2.50', $userChannel->fee_percent);
        $this->assertEquals('2.50', $account->fee_percent);
    }

    public function test_updateFee_zeros_all_descendants_when_zero(): void
    {
        // Create NestedSet tree: parent → child
        $parent = $this->createUser([
            '_lft'      => 1,
            '_rgt'      => 4,
            'parent_id' => null,
            'role'      => User::ROLE_MERCHANT,
        ]);
        $child = $this->createUser([
            '_lft'      => 2,
            '_rgt'      => 3,
            'parent_id' => $parent->getKey(),
            'role'      => User::ROLE_MERCHANT,
        ]);

        $channelGroup = $this->createChannelGroup();

        $parentUserChannel = $this->createUserChannel($parent->id, $channelGroup->id, [
            'fee_percent' => '3.00',
        ]);
        $childUserChannel = $this->createUserChannel($child->id, $channelGroup->id, [
            'fee_percent' => '2.00',
        ]);

        $this->util->updateFee($parentUserChannel, 0);

        $parentUserChannel->refresh();
        $childUserChannel->refresh();

        $this->assertEquals('0.00', $parentUserChannel->fee_percent);
        $this->assertEquals('0.00', $childUserChannel->fee_percent);
    }

    // ---------------------------------------------------------------
    //  updateMaxAmount()
    // ---------------------------------------------------------------

    public function test_updateMaxAmount_updates_channel_and_accounts(): void
    {
        $chain = $this->createChannelChain();
        $channelGroup = $chain['channelGroup'];
        $channelAmount = $chain['channelAmount'];

        $user = $this->createUser();
        $walletId = $this->createWallet($user->id);

        $userChannel = $this->createUserChannel($user->id, $channelGroup->id, [
            'max_amount' => '50000.00',
        ]);
        $account = $this->createUserChannelAccount($user->id, $walletId, $channelAmount->id, [
            'max_amount' => '50000.00',
        ]);

        $this->util->updateMaxAmount($userChannel, '99999.00');

        $userChannel->refresh();
        $account->refresh();

        $this->assertEquals('99999.00', $userChannel->max_amount);
        $this->assertEquals('99999.00', $account->max_amount);
    }

    // ---------------------------------------------------------------
    //  updateMinAmount()
    // ---------------------------------------------------------------

    public function test_updateMinAmount_updates_channel_and_accounts(): void
    {
        $chain = $this->createChannelChain();
        $channelGroup = $chain['channelGroup'];
        $channelAmount = $chain['channelAmount'];

        $user = $this->createUser();
        $walletId = $this->createWallet($user->id);

        $userChannel = $this->createUserChannel($user->id, $channelGroup->id, [
            'min_amount' => '10.00',
        ]);
        $account = $this->createUserChannelAccount($user->id, $walletId, $channelAmount->id, [
            'min_amount' => '10.00',
        ]);

        $this->util->updateMinAmount($userChannel, '50.00');

        $userChannel->refresh();
        $account->refresh();

        $this->assertEquals('50.00', $userChannel->min_amount);
        $this->assertEquals('50.00', $account->min_amount);
    }
}

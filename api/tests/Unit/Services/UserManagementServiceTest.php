<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\UserChannel;
use App\Services\UserManagementService;
use App\Utils\BCMathUtil;
use App\Utils\UserUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class UserManagementServiceTest extends TestCase
{
    use DatabaseTransactions;

    private UserManagementService $service;
    private $userUtil;
    private BCMathUtil $bcMath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userUtil = Mockery::mock(UserUtil::class);
        $this->bcMath = new BCMathUtil();

        $this->service = new UserManagementService($this->userUtil);
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

    private function createChannelCode(): string
    {
        $code = 'CH' . strtoupper(substr(uniqid(), 0, 10));
        DB::table('channels')->insert([
            'code'           => $code,
            'name'           => 'Test',
            'status'         => 1,
            'present_result' => 'amount',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
        return $code;
    }

    private function createChannelGroup(): int
    {
        return DB::table('channel_groups')->insertGetId([
            'channel_code' => $this->createChannelCode(),
            'fixed_amount' => 0,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function createUserChannel(int $userId, int $channelGroupId, array $overrides = []): UserChannel
    {
        $id = DB::table('user_channels')->insertGetId(array_merge([
            'user_id'          => $userId,
            'channel_group_id' => $channelGroupId,
            'fee_percent'      => '1.00',
            'status'           => UserChannel::STATUS_ENABLED,
            'created_at'       => now(),
            'updated_at'       => now(),
        ], $overrides));
        return UserChannel::find($id);
    }

    // ---------------------------------------------------------------
    //  abortIfUsernameNotAlnum
    // ---------------------------------------------------------------

    public function test_abortIfUsernameNotAlnum_passes_for_alphanumeric(): void
    {
        // Should not throw
        $this->service->abortIfUsernameNotAlnum('validUser123');

        $this->assertTrue(true); // reached without exception
    }

    public function test_abortIfUsernameNotAlnum_throws_for_special_chars(): void
    {
        $this->expectException(HttpException::class);

        $this->service->abortIfUsernameNotAlnum('invalid_user!');
    }

    // ---------------------------------------------------------------
    //  abortIfUsernameAlreadyExists
    // ---------------------------------------------------------------

    public function test_abortIfUsernameAlreadyExists_passes_when_not_exists(): void
    {
        $this->userUtil
            ->shouldReceive('usernameAlreadyExists')
            ->with('newuser')
            ->once()
            ->andReturn(false);

        // Should not throw
        $this->service->abortIfUsernameAlreadyExists('newuser');

        $this->assertTrue(true);
    }

    public function test_abortIfUsernameAlreadyExists_throws_when_exists(): void
    {
        $this->userUtil
            ->shouldReceive('usernameAlreadyExists')
            ->with('existinguser')
            ->once()
            ->andReturn(true);

        $this->expectException(HttpException::class);

        $this->service->abortIfUsernameAlreadyExists('existinguser');
    }

    // ---------------------------------------------------------------
    //  validateChannelFees
    // ---------------------------------------------------------------

    public function test_validateChannelFees_passes_when_fee_valid_gt(): void
    {
        $agent = $this->createUser(['role' => User::ROLE_PROVIDER]);
        $channelGroupId = $this->createChannelGroup();

        // Agent has fee_percent = 5.00
        $this->createUserChannel($agent->id, $channelGroupId, [
            'fee_percent' => '5.00',
            'status'      => UserChannel::STATUS_ENABLED,
        ]);

        // Child fee 3.00 is NOT greater than parent 5.00 => passes for 'gt' comparison
        $userChannels = [
            ['channel_group_id' => $channelGroupId, 'fee_percent' => '3.00'],
        ];

        $this->service->validateChannelFees($agent, $userChannels, $this->bcMath, 'gt');

        $this->assertTrue(true);
    }

    public function test_validateChannelFees_throws_when_child_fee_greater_than_parent(): void
    {
        $agent = $this->createUser(['role' => User::ROLE_PROVIDER]);
        $channelGroupId = $this->createChannelGroup();

        // Agent has fee_percent = 3.00
        $this->createUserChannel($agent->id, $channelGroupId, [
            'fee_percent' => '3.00',
            'status'      => UserChannel::STATUS_ENABLED,
        ]);

        // Child fee 5.00 IS greater than parent 3.00 => should abort for 'gt'
        $userChannels = [
            ['channel_group_id' => $channelGroupId, 'fee_percent' => '5.00'],
        ];

        $this->expectException(HttpException::class);

        $this->service->validateChannelFees($agent, $userChannels, $this->bcMath, 'gt');
    }

    public function test_validateChannelFees_throws_when_parent_channel_disabled(): void
    {
        $agent = $this->createUser(['role' => User::ROLE_PROVIDER]);
        $channelGroupId = $this->createChannelGroup();

        // Agent channel is disabled
        $this->createUserChannel($agent->id, $channelGroupId, [
            'fee_percent' => '3.00',
            'status'      => UserChannel::STATUS_DISABLED,
        ]);

        $userChannels = [
            ['channel_group_id' => $channelGroupId, 'fee_percent' => '2.00'],
        ];

        $this->expectException(HttpException::class);

        $this->service->validateChannelFees($agent, $userChannels, $this->bcMath, 'gt');
    }

    public function test_validateChannelFees_throws_when_parent_fee_null(): void
    {
        $agent = $this->createUser(['role' => User::ROLE_PROVIDER]);
        $channelGroupId = $this->createChannelGroup();

        // Agent channel has null fee_percent
        $this->createUserChannel($agent->id, $channelGroupId, [
            'fee_percent' => null,
            'status'      => UserChannel::STATUS_ENABLED,
        ]);

        $userChannels = [
            ['channel_group_id' => $channelGroupId, 'fee_percent' => '2.00'],
        ];

        $this->expectException(HttpException::class);

        $this->service->validateChannelFees($agent, $userChannels, $this->bcMath, 'gt');
    }

    public function test_validateChannelFees_skips_null_fee_percent(): void
    {
        $agent = $this->createUser(['role' => User::ROLE_PROVIDER]);
        $channelGroupId = $this->createChannelGroup();

        // No need to create agent channel since null fee_percent is skipped
        $userChannels = [
            ['channel_group_id' => $channelGroupId, 'fee_percent' => null],
        ];

        // Should not throw - null fee_percent entries are skipped entirely
        $this->service->validateChannelFees($agent, $userChannels, $this->bcMath, 'gt');

        $this->assertTrue(true);
    }
}

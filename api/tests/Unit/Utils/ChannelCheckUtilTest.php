<?php

namespace Tests\Unit\Utils;

use App\Models\User;
use App\Models\UserChannel;
use App\Utils\ChannelCheckUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ChannelCheckUtilTest extends TestCase
{
    use DatabaseTransactions;

    private ChannelCheckUtil $util;

    protected function setUp(): void
    {
        parent::setUp();
        $this->util = new ChannelCheckUtil();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createUser(array $overrides = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name' => 'test_user',
            'username' => 'test_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => User::ROLE_MERCHANT,
            'secret_key' => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return User::find($id);
    }

    private function createChannelGroup(): int
    {
        return DB::table('channel_groups')->insertGetId([
            'channel_code' => 'TG' . substr(uniqid(), -6),
            'fixed_amount' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUserChannel(int $userId, int $channelGroupId, array $overrides = []): UserChannel
    {
        $id = DB::table('user_channels')->insertGetId(array_merge([
            'user_id' => $userId,
            'channel_group_id' => $channelGroupId,
            'status' => UserChannel::STATUS_ENABLED,
            'fee_percent' => '3.00',
            'min_amount' => '10.00',
            'max_amount' => '10000.00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return UserChannel::find($id);
    }

    // ---------------------------------------------------------------
    // checkChannelFail()
    // ---------------------------------------------------------------

    public function test_checkChannelFail_returns_false_when_no_channels(): void
    {
        $child = $this->createUser();
        $parent = $this->createUser(['username' => 'parent_' . uniqid()]);

        // child has no enabled channels
        $result = $this->util->checkChannelFail($child->id, $parent->id);

        $this->assertFalse($result);
    }

    public function test_checkChannelFail_returns_true_when_parent_missing_channel(): void
    {
        $child = $this->createUser();
        $parent = $this->createUser(['username' => 'parent_' . uniqid()]);
        $groupId = $this->createChannelGroup();

        // child has a channel, parent does not
        $this->createUserChannel($child->id, $groupId);

        $result = $this->util->checkChannelFail($child->id, $parent->id);

        $this->assertTrue($result);
    }

    public function test_checkChannelFail_returns_true_when_parent_fee_higher(): void
    {
        $child = $this->createUser();
        $parent = $this->createUser(['username' => 'parent_' . uniqid()]);
        $groupId = $this->createChannelGroup();

        // child has fee_percent = 2.00, parent has fee_percent = 5.00
        // parent's fee > child's fee => fail
        $this->createUserChannel($child->id, $groupId, ['fee_percent' => '2.00']);
        $this->createUserChannel($parent->id, $groupId, ['fee_percent' => '5.00']);

        $result = $this->util->checkChannelFail($child->id, $parent->id);

        $this->assertTrue($result);
    }

    public function test_checkChannelFail_returns_false_when_valid(): void
    {
        $child = $this->createUser();
        $parent = $this->createUser(['username' => 'parent_' . uniqid()]);
        $groupId = $this->createChannelGroup();

        // parent fee <= child fee => valid
        $this->createUserChannel($child->id, $groupId, ['fee_percent' => '5.00']);
        $this->createUserChannel($parent->id, $groupId, ['fee_percent' => '3.00']);

        $result = $this->util->checkChannelFail($child->id, $parent->id);

        $this->assertFalse($result);
    }

    // ---------------------------------------------------------------
    // abortForbiddenIfcheckChannelFailed()
    // ---------------------------------------------------------------

    public function test_abortForbiddenIfcheckChannelFailed_throws_when_fail(): void
    {
        $child = $this->createUser();
        $parent = $this->createUser(['username' => 'parent_' . uniqid()]);
        $groupId = $this->createChannelGroup();

        // child has channel, parent does not => checkChannelFail returns true
        $this->createUserChannel($child->id, $groupId);

        $this->expectException(HttpException::class);

        $this->util->abortForbiddenIfcheckChannelFailed($child->id, $parent->id);
    }
}

<?php

namespace Tests\Unit\Utils;

use App\Models\User;
use App\Models\WhitelistedIp;
use App\Utils\WhitelistedIpManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WhitelistedIpManagerTest extends TestCase
{
    use DatabaseTransactions;

    private WhitelistedIpManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new WhitelistedIpManager();
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

    private function createWhitelistedIp(int $userId, string $ipv4, int $type): WhitelistedIp
    {
        return WhitelistedIp::create([
            'user_id' => $userId,
            'ipv4' => $ipv4,
            'type' => $type,
        ]);
    }

    private function createRequestFromIp(string $ip): Request
    {
        return Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
    }

    // ---------------------------------------------------------------
    // isInvalidIpv4()
    // ---------------------------------------------------------------

    public function test_isInvalidIpv4_returns_false_for_valid_ip(): void
    {
        $this->assertFalse($this->manager->isInvalidIpv4('192.168.1.1'));
    }

    public function test_isInvalidIpv4_returns_true_for_invalid_ip(): void
    {
        $this->assertTrue($this->manager->isInvalidIpv4('not-an-ip'));
    }

    public function test_isInvalidIpv4_returns_true_for_empty_string(): void
    {
        $this->assertTrue($this->manager->isInvalidIpv4(''));
    }

    // ---------------------------------------------------------------
    // extractIpFromRequest()
    // ---------------------------------------------------------------

    public function test_extractIpFromRequest_returns_last_ip(): void
    {
        $request = $this->createRequestFromIp('10.0.0.1');

        $result = $this->manager->extractIpFromRequest($request);

        $this->assertSame('10.0.0.1', $result);
    }

    // ---------------------------------------------------------------
    // loginWhitelistedIpIsEmptyFor()
    // ---------------------------------------------------------------

    public function test_loginWhitelistedIpIsEmptyFor_returns_true_when_no_records(): void
    {
        $user = $this->createUser();

        $this->assertTrue($this->manager->loginWhitelistedIpIsEmptyFor($user));
    }

    public function test_loginWhitelistedIpIsEmptyFor_returns_false_when_records_exist(): void
    {
        $user = $this->createUser();
        $this->createWhitelistedIp($user->id, '192.168.1.1', WhitelistedIp::TYPE_LOGIN);

        $this->assertFalse($this->manager->loginWhitelistedIpIsEmptyFor($user));
    }

    // ---------------------------------------------------------------
    // loginWhitelistedIpExistsFor()
    // ---------------------------------------------------------------

    public function test_loginWhitelistedIpExistsFor_returns_true_when_ip_matches(): void
    {
        $user = $this->createUser();
        $this->createWhitelistedIp($user->id, '192.168.1.1', WhitelistedIp::TYPE_LOGIN);

        $this->assertTrue($this->manager->loginWhitelistedIpExistsFor($user, '192.168.1.1'));
    }

    public function test_loginWhitelistedIpExistsFor_returns_false_when_ip_differs(): void
    {
        $user = $this->createUser();
        $this->createWhitelistedIp($user->id, '192.168.1.1', WhitelistedIp::TYPE_LOGIN);

        $this->assertFalse($this->manager->loginWhitelistedIpExistsFor($user, '10.0.0.1'));
    }

    // ---------------------------------------------------------------
    // isAllowedToLoginFromRequest()
    // ---------------------------------------------------------------

    public function test_isAllowedToLoginFromRequest_returns_false_when_no_user(): void
    {
        $request = $this->createRequestFromIp('1.2.3.4');
        // No user resolver set — request->user() returns null

        $this->assertFalse($this->manager->isAllowedToLoginFromRequest($request));
    }

    public function test_isAllowedToLoginFromRequest_returns_true_when_no_whitelist(): void
    {
        $user = $this->createUser();
        $request = $this->createRequestFromIp('1.2.3.4');
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($this->manager->isAllowedToLoginFromRequest($request));
    }

    public function test_isAllowedToLoginFromRequest_returns_true_when_ip_whitelisted(): void
    {
        $user = $this->createUser();
        $this->createWhitelistedIp($user->id, '1.2.3.4', WhitelistedIp::TYPE_LOGIN);

        $request = $this->createRequestFromIp('1.2.3.4');
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($this->manager->isAllowedToLoginFromRequest($request));
    }

    public function test_isAllowedToLoginFromRequest_returns_false_when_ip_not_whitelisted(): void
    {
        $user = $this->createUser();
        $this->createWhitelistedIp($user->id, '1.2.3.4', WhitelistedIp::TYPE_LOGIN);

        $request = $this->createRequestFromIp('5.6.7.8');
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($this->manager->isAllowedToLoginFromRequest($request));
    }

    // ---------------------------------------------------------------
    // isNotAllowedToUseThirdPartyApi()
    // ---------------------------------------------------------------

    public function test_isNotAllowedToUseThirdPartyApi_returns_false_when_no_whitelist(): void
    {
        $user = $this->createUser();
        $request = $this->createRequestFromIp('1.2.3.4');

        $this->assertFalse($this->manager->isNotAllowedToUseThirdPartyApi($user, $request));
    }

    public function test_isNotAllowedToUseThirdPartyApi_returns_false_when_ip_whitelisted(): void
    {
        $user = $this->createUser();
        $this->createWhitelistedIp($user->id, '1.2.3.4', WhitelistedIp::TYPE_API);

        $request = $this->createRequestFromIp('1.2.3.4');

        $this->assertFalse($this->manager->isNotAllowedToUseThirdPartyApi($user, $request));
    }

    public function test_isNotAllowedToUseThirdPartyApi_returns_true_when_ip_not_whitelisted(): void
    {
        $user = $this->createUser();
        $this->createWhitelistedIp($user->id, '1.2.3.4', WhitelistedIp::TYPE_API);

        $request = $this->createRequestFromIp('5.6.7.8');

        $this->assertTrue($this->manager->isNotAllowedToUseThirdPartyApi($user, $request));
    }

    // ---------------------------------------------------------------
    // userHasMoreThanOneLoginWhitelistedIp()
    // ---------------------------------------------------------------

    public function test_userHasMoreThanOneLoginWhitelistedIp_returns_true_when_multiple(): void
    {
        $user = $this->createUser();
        $this->createWhitelistedIp($user->id, '1.1.1.1', WhitelistedIp::TYPE_LOGIN);
        $this->createWhitelistedIp($user->id, '2.2.2.2', WhitelistedIp::TYPE_LOGIN);

        $this->assertTrue($this->manager->userHasMoreThanOneLoginWhitelistedIp($user));
    }

    public function test_userHasMoreThanOneLoginWhitelistedIp_returns_false_when_single(): void
    {
        $user = $this->createUser();
        $this->createWhitelistedIp($user->id, '1.1.1.1', WhitelistedIp::TYPE_LOGIN);

        $this->assertFalse($this->manager->userHasMoreThanOneLoginWhitelistedIp($user));
    }
}

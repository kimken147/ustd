<?php

namespace Tests\Unit\Utils;

use App\Models\FeatureToggle;
use App\Models\User;
use App\Notifications\AdminLogin;
use App\Notifications\AdminResetGoogle2faSecret;
use App\Notifications\AdminResetPassword;
use App\Notifications\BusyPayingBlocked;
use App\Notifications\LoginThrottle;
use App\Notifications\UserChannelAccountTooManyPayingTimeout;
use App\Repository\FeatureToggleRepository;
use App\Utils\NotificationUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\TestCase;

class NotificationUtilTest extends TestCase
{
    use DatabaseTransactions;

    /** @var FeatureToggleRepository|Mockery\MockInterface */
    private $featureToggleRepository;

    private NotificationUtil $notificationUtil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->featureToggleRepository = Mockery::mock(FeatureToggleRepository::class);
        $this->notificationUtil = new NotificationUtil($this->featureToggleRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
            'role' => User::ROLE_ADMIN,
            'secret_key' => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'god' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return User::find($id);
    }

    private function setTelegramConfig(): void
    {
        Config::set('services.telegram-bot-api.token', 'test-token');
        Config::set('services.telegram-bot-api.system-admin-group-id', '-100123456');
        Config::set('services.telegram-bot-api.engineer-leader-group-id', '-100789012');
    }

    private function clearTelegramConfig(): void
    {
        Config::set('services.telegram-bot-api.token', null);
        Config::set('services.telegram-bot-api.system-admin-group-id', null);
    }

    // ---------------------------------------------------------------
    // configSet()
    // ---------------------------------------------------------------

    public function test_configSet_returns_true_when_config_set(): void
    {
        $this->setTelegramConfig();

        $this->assertTrue($this->notificationUtil->configSet());
    }

    public function test_configSet_returns_false_when_config_missing(): void
    {
        $this->clearTelegramConfig();

        $this->assertFalse($this->notificationUtil->configSet());
    }

    // ---------------------------------------------------------------
    // notifyGroups()
    // ---------------------------------------------------------------

    public function test_notifyGroups_returns_correct_group_for_known_notification(): void
    {
        $this->setTelegramConfig();

        // Create a real notification instance — constructor requires a Transaction
        $transaction = Mockery::mock(\App\Models\Transaction::class);
        $notification = new UserChannelAccountTooManyPayingTimeout($transaction);

        $groups = $this->notificationUtil->notifyGroups($notification);

        $this->assertIsArray($groups);
        $this->assertCount(1, $groups);
        $this->assertSame(config('services.telegram-bot-api.system-admin-group-id'), $groups[0]);
    }

    public function test_notifyGroups_returns_empty_for_unknown_notification(): void
    {
        $notification = Mockery::mock(AdminLogin::class);

        $groups = $this->notificationUtil->notifyGroups($notification);

        $this->assertIsArray($groups);
        $this->assertEmpty($groups);
    }

    // ---------------------------------------------------------------
    // notifyAdminLogin()
    // ---------------------------------------------------------------

    public function test_notifyAdminLogin_skips_when_feature_disabled(): void
    {
        Notification::fake();
        $this->setTelegramConfig();

        $admin = $this->createUser(['god' => false]);

        $this->featureToggleRepository->shouldReceive('enabled')
            ->with(FeatureToggle::NOTIFY_ADMIN_LOGIN)
            ->once()
            ->andReturn(false);

        $this->notificationUtil->notifyAdminLogin($admin, '1.2.3.4');

        Notification::assertNothingSent();
    }

    public function test_notifyAdminLogin_sends_for_god_user_to_engineer_group(): void
    {
        Notification::fake();
        $this->setTelegramConfig();

        $admin = $this->createUser(['god' => true]);

        // god user short-circuits: sends to engineer group, then returns early
        // because $admin->god is true in the second condition block
        $this->featureToggleRepository->shouldReceive('enabled')
            ->with(FeatureToggle::NOTIFY_ADMIN_LOGIN)
            ->andReturn(true);

        $this->notificationUtil->notifyAdminLogin($admin, '1.2.3.4');

        // Should send to engineer-leader-group only (god user skips system-admin-group)
        Notification::assertSentOnDemand(AdminLogin::class, function ($notification, $channels, $notifiable) {
            return $notifiable->routes['telegram'] === config('services.telegram-bot-api.engineer-leader-group-id');
        });
    }

    // ---------------------------------------------------------------
    // notifyAdminResetGoogle2faSecret()
    // ---------------------------------------------------------------

    public function test_notifyAdminResetGoogle2faSecret_sends_when_enabled(): void
    {
        Notification::fake();
        $this->setTelegramConfig();

        $admin = $this->createUser();
        $targetUser = $this->createUser(['username' => 'target_' . uniqid()]);

        $this->featureToggleRepository->shouldReceive('enabled')
            ->with(FeatureToggle::NOTIFY_ADMIN_RESET_GOOGLE2FA_SECRET)
            ->once()
            ->andReturn(true);

        $this->notificationUtil->notifyAdminResetGoogle2faSecret($admin, $targetUser, '1.2.3.4');

        Notification::assertSentOnDemand(AdminResetGoogle2faSecret::class);
    }

    public function test_notifyAdminResetGoogle2faSecret_skips_when_disabled(): void
    {
        Notification::fake();
        $this->setTelegramConfig();

        $admin = $this->createUser();
        $targetUser = $this->createUser(['username' => 'target_' . uniqid()]);

        $this->featureToggleRepository->shouldReceive('enabled')
            ->with(FeatureToggle::NOTIFY_ADMIN_RESET_GOOGLE2FA_SECRET)
            ->once()
            ->andReturn(false);

        $this->notificationUtil->notifyAdminResetGoogle2faSecret($admin, $targetUser, '1.2.3.4');

        Notification::assertNothingSent();
    }

    // ---------------------------------------------------------------
    // notifyAdminResetPassword()
    // ---------------------------------------------------------------

    public function test_notifyAdminResetPassword_sends_when_enabled(): void
    {
        Notification::fake();
        $this->setTelegramConfig();

        $admin = $this->createUser();
        $targetUser = $this->createUser(['username' => 'target_' . uniqid()]);

        $this->featureToggleRepository->shouldReceive('enabled')
            ->with(FeatureToggle::NOTIFY_ADMIN_RESET_PASSWORD)
            ->once()
            ->andReturn(true);

        $this->notificationUtil->notifyAdminResetPassword($admin, $targetUser, '1.2.3.4');

        Notification::assertSentOnDemand(AdminResetPassword::class);
    }

    // ---------------------------------------------------------------
    // notifyBusyPayingBlocked()
    // ---------------------------------------------------------------

    public function test_notifyBusyPayingBlocked_sends_when_config_set(): void
    {
        Notification::fake();
        $this->setTelegramConfig();

        $merchant = $this->createUser(['role' => User::ROLE_MERCHANT]);

        $this->notificationUtil->notifyBusyPayingBlocked($merchant, 'ORD123', '1.2.3.4', '100.00');

        Notification::assertSentOnDemand(BusyPayingBlocked::class);
    }

    public function test_notifyBusyPayingBlocked_skips_when_config_not_set(): void
    {
        Notification::fake();
        $this->clearTelegramConfig();

        $merchant = $this->createUser(['role' => User::ROLE_MERCHANT]);

        $this->notificationUtil->notifyBusyPayingBlocked($merchant, 'ORD123', '1.2.3.4', '100.00');

        Notification::assertNothingSent();
    }

    // ---------------------------------------------------------------
    // notifyLoginThrottle()
    // ---------------------------------------------------------------

    public function test_notifyLoginThrottle_sends_when_config_set(): void
    {
        Notification::fake();
        $this->setTelegramConfig();

        $this->notificationUtil->notifyLoginThrottle('bad_user', '1.2.3.4');

        Notification::assertSentOnDemand(LoginThrottle::class);
    }
}

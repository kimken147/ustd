<?php


namespace App\Utils;


use App\Models\FeatureToggle;
use App\Models\User;
use App\Notifications\AdminLogin;
use App\Notifications\AdminResetGoogle2faSecret;
use App\Notifications\AdminResetPassword;
use App\Notifications\AdminResetSecret;
use App\Notifications\AdminUpdateBalance;
use App\Notifications\BusyPayingBlocked;
use App\Notifications\UserChannelAccountTooManyPayingTimeout;
use App\Repository\FeatureToggleRepository;
use Illuminate\Support\Facades\Notification;

class NotificationUtil
{

    /**
     * @var FeatureToggleRepository
     */
    public $featureToggleRepository;

    public function __construct(FeatureToggleRepository $featureToggleRepository)
    {
        $this->featureToggleRepository = $featureToggleRepository;
    }

    public function notify(\Illuminate\Notifications\Notification $notification)
    {
        foreach ($this->notifyGroups($notification) as $notifyGroup) {
            Notification::route('telegram', $notifyGroup)->notify($notification);
        }
    }

    public function notifyGroups(\Illuminate\Notifications\Notification $notification)
    {
        return data_get([
            UserChannelAccountTooManyPayingTimeout::class => [
                config('services.telegram-bot-api.system-admin-group-id'),
            ],
        ], get_class($notification), []);
    }

    public function notifyAdminLogin(User $admin, string $ipv4)
    {
        if ($admin->god) {
            Notification::route('telegram', config('services.telegram-bot-api.engineer-leader-group-id'))
                ->notify(
                    new AdminLogin(
                        $admin,
                        $ipv4
                    )
                );
        }

        if (
            !$this->featureToggleRepository->enabled(FeatureToggle::NOTIFY_ADMIN_LOGIN)
            || !$this->configSet()
            || $admin->god
        ) {
            return;
        }

        Notification::route('telegram', config('services.telegram-bot-api.system-admin-group-id'))
            ->notify(
                new AdminLogin(
                    $admin,
                    $ipv4
                )
            );
    }

    public function configSet()
    {
        return !empty(config('services.telegram-bot-api.token')) && !empty(config('services.telegram-bot-api.system-admin-group-id'));
    }

    public function notifyAdminResetGoogle2faSecret(User $admin, User $targetUser, string $ipv4)
    {
        if (
            !$this->featureToggleRepository->enabled(FeatureToggle::NOTIFY_ADMIN_RESET_GOOGLE2FA_SECRET)
            || !$this->configSet()
        ) {
            return;
        }

        Notification::route('telegram', config('services.telegram-bot-api.system-admin-group-id'))
            ->notify(
                new AdminResetGoogle2faSecret(
                    $admin,
                    $targetUser,
                    $ipv4
                )
            );
    }

    public function notifyAdminResetPassword(User $admin, User $targetUser, string $ipv4)
    {
        if (
            !$this->featureToggleRepository->enabled(FeatureToggle::NOTIFY_ADMIN_RESET_PASSWORD)
            || !$this->configSet()
        ) {
            return;
        }

        Notification::route('telegram', config('services.telegram-bot-api.system-admin-group-id'))
            ->notify(
                new AdminResetPassword(
                    $admin,
                    $targetUser,
                    $ipv4
                )
            );
    }

    public function notifyAdminResetSecret(User $admin, User $targetUser, string $ipv4)
    {
        if (
            !$this->featureToggleRepository->enabled(FeatureToggle::NOTIFY_ADMIN_RESET_PASSWORD)
            || !$this->configSet()
        ) {
            return;
        }

        Notification::route('telegram', config('services.telegram-bot-api.system-admin-group-id'))
            ->notify(
                new AdminResetSecret(
                    $admin,
                    $targetUser,
                    $ipv4
                )
            );
    }

    public function notifyAdminUpdateBalance(User $admin, User $targetUser, array $delta, string $note, string $ipv4)
    {
        if (
            !$this->featureToggleRepository->enabled(FeatureToggle::NOTIFY_ADMIN_UPDATE_BALANCE)
            || !$this->configSet()
        ) {
            return;
        }

        Notification::route('telegram', config('services.telegram-bot-api.system-admin-group-id'))
            ->notify(
                new AdminUpdateBalance(
                    $admin,
                    $targetUser,
                    $delta,
                    $note ?? '',
                    $ipv4
                )
            );
    }

    public function notifyBusyPayingBlocked(User $merchant, string $orderNumber, string $ipv4, string $amount)
    {
        if (!$this->configSet()) {
            return;
        }

        Notification::route('telegram', config('services.telegram-bot-api.system-admin-group-id'))
            ->notify(new BusyPayingBlocked($merchant, $orderNumber, $ipv4, $amount));
    }

    public function notifyLoginThrottle(string $tryingUsername, string $ipv4)
    {
        if (!$this->configSet()) {
            return;
        }

        Notification::route('telegram', config('services.telegram-bot-api.system-admin-group-id'))
            ->notify(new \App\Notifications\LoginThrottle($tryingUsername, $ipv4));
    }
}

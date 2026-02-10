<?php

namespace App\Console;

use App\Console\Commands\DisableNonDepositProviders;
use App\Console\Commands\ClearUserChannelAccountDailyTotal;
use App\Console\Commands\ClearUserChannelAccountMonthlyTotal;
use App\Console\Commands\UpdateTransactionSearchFields;
use App\Console\Commands\SyncThirdchannelDaifuOrder;
use App\Console\Commands\CheckThirdchannelBalance;
use App\Console\Commands\SyncThirdchannelBalance;


use App\Console\Commands\CheckDelayedProviderDeposit;
use App\Console\Commands\DisableNonLoginUser;
use App\Console\Commands\DisableTimeLimitUserChannelAccount;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Spatie\ShortSchedule\ShortSchedule;

class Kernel extends ConsoleKernel
{

    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Define the application's command schedule.
     *
     * @param  Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command(CheckDelayedProviderDeposit::class)->everyMinute()->onOneServer();
        $schedule->command(CheckThirdchannelBalance::class)->everyMinute()->onOneServer();
        $schedule->command(SyncThirdchannelBalance::class)->everyMinute()->onOneServer();
        $schedule->command(SyncThirdchannelDaifuOrder::class)->everyMinute()->onOneServer();

        $schedule->command(UpdateTransactionSearchFields::class)->everyFiveMinutes()->onOneServer();

        $schedule->command(DisableTimeLimitUserChannelAccount::class, ['user_channel_account' => null])->everyTenMinutes()->onOneServer();

        $schedule->command(DisableNonLoginUser::class)->daily()->onOneServer();
        $schedule->command(DisableNonDepositProviders::class)->daily()->onOneServer();
        $schedule->command(ClearUserChannelAccountDailyTotal::class)->daily()->onOneServer();

        $schedule->command(ClearUserChannelAccountMonthlyTotal::class)->monthly()->onOneServer();


    }

    protected function shortSchedule(ShortSchedule $shortSchedule)
    {
        //
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ClearUserChannelAccountDailyTotal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:daily-total';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清除收款帳號當日已收款額度';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        \DB::table('user_channel_accounts')->update(['daily_total' => 0, 'withdraw_daily_total' => 0]);
    }
}

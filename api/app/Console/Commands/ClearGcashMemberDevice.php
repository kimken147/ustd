<?php

namespace App\Console\Commands;

use App\Model\MemberDevice;
use App\Model\UserChannelAccount;
use Illuminate\Console\Command;

class ClearGcashMemberDevice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gcash:clear-device';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清除暫存的登入資料';

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
        foreach (UserChannelAccount::get('account') as $account) {
            MemberDevice::where('device', $account)->where('created_at', '<=', now()->subDays(14))->delete();
        }
    }
}

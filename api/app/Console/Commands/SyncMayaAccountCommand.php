<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Model\Channel;
use App\Model\UserChannelAccount;
use App\Jobs\SyncMayaAccountJob;

class SyncMayaAccountCommand extends Command
{

    /**
     * @var string
     */
    protected $description = '執行同步 Maya 帳號';
    /**
     * @var string
     */
    protected $signature = 'maya:sync-account';

    public function handle()
    {
        if (config('app.region') != 'ph') {
            return;
        }

        // $accounts = UserChannelAccount::with('user')
        //     ->where('channel_code', Channel::CODE_MAYA)
        //     ->where('detail->sync_status', "init")
        //     ->get();
        $account = UserChannelAccount::with("user")
            ->find(20);

        SyncMayaAccountJob::dispatch($account, "need_otp", "123");

        // foreach ($accounts as $account) {
        //     // $detail = $account->detail;
        //     // $detail['sync_status'] = 'password_login';
        //     // $account->update(['detail' => $detail]);
        //     // SyncMayaAccountJob::dispatch($account, 'password_login');
        //     // SyncMayaAccountJob::dispatch($account, 'otp_login');
        // }
    }
}

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Model\Channel;
use App\Model\UserChannelAccount;
use App\Jobs\SyncGcashAccount;

class ProcessGcashAutoSyncAccount extends Command
{
    protected $description = '執行自動同步 GCash 帳號';

    protected $signature = 'gcash:auto-sync-account';

    public function handle()
    {
        $accounts = UserChannelAccount::with('user')
            ->whereIn('channel_code', [Channel::CODE_GCASH])
            ->where('status', UserChannelAccount::STATUS_ONLINE)
            ->where('auto_sync', true)
            ->get();

        foreach ($accounts as $account) {
            SyncGcashAccount::dispatch($account->id, 'init');
        }
    }
}

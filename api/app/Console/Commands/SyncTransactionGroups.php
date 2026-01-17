<?php

namespace App\Console\Commands;

use App\Models\TransactionGroup;
use Illuminate\Console\Command;
use App\Models\UserChannelAccount;
use Illuminate\Support\Facades\DB;

class SyncTransactionGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:transaction-groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '同步代收/付專線';

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
        $groups = TransactionGroup::with('owner', 'worker', 'worker.userChannelAccounts')->get();

        foreach ($groups as $group) {
            DB::transaction(function () use ($group) {
                $group->userChannelAccounts()->detach();
                $accounts = $group->worker->userChannelAccounts;
                $group->userChannelAccounts()->syncWithoutDetaching($accounts);    
            });
        }
    }
}

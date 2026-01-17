<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\Channel;
use App\Models\UserChannelAccount;
use App\Utils\TransactionUtil;
use App\Models\TransactionGroup;
use App\Models\Transaction;
use App\Models\User;
use App\Utils\TransactionFactory;
use App\Jobs\SyncGcashAccount;
use App\Repository\FeatureToggleRepository;
use App\Models\FeatureToggle;

class ProcessGcashSyncAccount extends Command
{

    /**
     * @var string
     */
    protected $description = '執行同步 GCash 帳號';
    /**
     * @var string
     */
    protected $signature = 'gcash:sync-account';

    public function handle(TransactionFactory $transactionFactory, FeatureToggleRepository $featureToggleRepository)
    {
        if (config('app.region') != 'ph') {
            return;
        }

        $accounts = UserChannelAccount::with('user')
            ->whereIn('channel_code', [Channel::CODE_GCASH])
            ->where('detail->sync_status', 'need_mpin')
            ->get();

        foreach ($accounts as $account) {
            $detail = $account->detail;

            if ($detail['sync_status'] == 'need_mpin' && isset($detail['mpin'])) {

                $detail['sync_status'] = 'mpin_processing';
                $account->update(['detail' => $detail]);
                SyncGcashAccount::dispatch($account->id, 'mpin');
            }
        }
    }
}

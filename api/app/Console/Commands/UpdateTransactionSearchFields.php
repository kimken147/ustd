<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Model\Transaction;

class UpdateTransactionSearchFields extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transaction:update:search:fields';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新transactions搜尋欄位';

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
        $startAt = now()->subMinutes(20);
        $endAt = now()->subMinutes(10);

        $transactions = Transaction::whereIn('status', [
            Transaction::STATUS_MANUAL_SUCCESS,
            Transaction::STATUS_SUCCESS,
            Transaction::STATUS_PAYING_TIMED_OUT,
            Transaction::STATUS_FAILED,
        ])
        ->whereIn('type', [Transaction::TYPE_PAUFEN_TRANSACTION, Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW, Transaction::TYPE_INTERNAL_TRANSFER])
        ->whereBetween('created_at', [$startAt, $endAt])->get();

        foreach ($transactions as $transaction) {
            $update = [];
            if (!$transaction->_search2) {
                if ($transaction->type == Transaction::TYPE_PAUFEN_TRANSACTION) {
                    $update['_search2'] = data_get($transaction->to_channel_account, 'mobile_number');
                } else if (in_array($transaction->type, [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_INTERNAL_TRANSFER])) {
                    $update['_search2'] = data_get($transaction->from_channel_account, 'bank_card_number');
                }
            }

            if (!$transaction->_from_channel_account) {
                if ($transaction->from_channel_account_id) {
                    $update['_from_channel_account'] = data_get($transaction->from_channel_account, 'account');
                }
            }

            if (!empty($update)) {
                Transaction::where('id', $transaction->id)->update($update);
            }
        }
    }
}

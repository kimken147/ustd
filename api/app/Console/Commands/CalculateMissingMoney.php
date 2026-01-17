<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Model\User;
use App\Model\WalletHistory;

class CalculateMissingMoney extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculate:missing-money {date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '計算消失的錢額';

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
        $date = $this->argument('date');
        $start = Carbon::parse($date)->startOfDay();
        $end = Carbon::parse($date)->endOfDay();

        $providers = User::where('role', User::ROLE_PROVIDER)->get();

        print("碼商================================\n");
        foreach ($providers as $provider) {
            $histories = WalletHistory::where('user_id', $provider->id)->whereBetween('created_at', [$start, $end])->get();
            if ($histories->isEmpty()) continue;

            $first = $histories->first();
            $delta = $histories->pluck('delta');
            $last = $histories->last()->result;

            $firstProfit = bcsub($first->result['profit'], $first->delta['profit'], 2);
            $firstBalance = bcsub($first->result['balance'], $first->delta['balance'], 2);
            $firstFrozen = bcsub($first->result['frozen_balance'], $first->delta['frozen_balance'], 2);

            $profit = bcadd($firstProfit, bcsub($delta->sum('profit'), $last['profit'], 2), 2);
            $balance = bcadd($firstBalance, bcsub($delta->sum('balance'), $last['balance'], 2), 2);
            $frozenBalance = bcadd($firstFrozen, bcsub($delta->sum('frozen_balance'), $last['frozen_balance'], 2), 2);

            printf("%20s 紅利:%10s 餘額:%10s 凍結:%10s\n", $provider->username, $profit, $balance, $frozenBalance);
        }

        print("\n商戶================================\n");

        $merchants = User::where('role', User::ROLE_MERCHANT)->get();
        foreach ($merchants as $merchant) {
            $histories = WalletHistory::where('user_id', $merchant->id)->whereBetween('created_at', [$start, $end])->get();
            if ($histories->isEmpty()) continue;

            $first = $histories->first();
            $delta = $histories->pluck('delta');
            $last = $histories->last()->result;

            $firstBalance = bcsub($first->result['balance'], $first->delta['balance'], 2);
            $firstFrozen = bcsub($first->result['frozen_balance'], $first->delta['frozen_balance'], 2);

            $balance = bcadd($firstBalance, bcsub($delta->sum('balance'), $last['balance'], 2), 2);
            $frozenBalance = bcadd($firstFrozen, bcsub($delta->sum('frozen_balance'), $last['frozen_balance'], 2), 2);

            printf("%20s 餘額:%10s 凍結:%10s\n", $merchant->username, $balance, $frozenBalance);
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Jobs\MayaDaifu;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Services\Transaction\DaiFuService;
use App\Utils\TransactionFactory;
use Illuminate\Console\Command;

class MatchMayaDaifu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maya:auto-daifu';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '執行maya自動代付';

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
     * @return int
     */
    public function handle(DaiFuService $daiFuService, TransactionFactory $transactionFactory)
    {
        if (!$daiFuService->checkAutoIsValid("ph")) return;

        $accounts = UserChannelAccount::with('user')
            ->where('channel_code', Channel::CODE_MAYA)
            ->where('status', UserChannelAccount::STATUS_ONLINE)
            ->where('type', '!=', UserChannelAccount::TYPE_DEPOSIT)
            ->where('is_auto', true)
            ->whereDoesntHave('payingDaifu')
            ->orderByDesc('type')
            ->orderBy('balance')
            ->get();

        foreach ($accounts as $account) {
            $user = $account->user;

            if (!$user->deposit_enable || !$user->paufen_deposit_enable) {
                continue;
            }

            $transactionGroups = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)
                ->where(function ($query) use ($user) {
                    $query->where('worker_id', $user->id)->where('personal_enable', false);
                    $query->orWhereIn('worker_id', User::whereAncestorOrSelf($user)->select(['id']))->where('personal_enable', true);
                });

            // $transactionGroupExists = $transactionGroups->exists();

            // 查出該出款帳號，目前可以代付的代付單
            $matchingDeposit = Transaction::where(function ($builder) use ($transactionGroups) {
                $builder->where(function ($query) use ($transactionGroups) {
                    $query->where('type', Transaction::TYPE_PAUFEN_WITHDRAW)
                        ->when($transactionGroups->exists(), function ($matchingDeposits) use ($transactionGroups) {
                            $matchingDeposits->whereIn('from_id', $transactionGroups->select('owner_id'));
                        })
                        ->when(!$transactionGroups->exists(), function ($matchingDeposits) {
                            $matchingDeposits->whereDoesntHave('from.matchingDepositGroups');
                        });
                })->orWhere(function ($query) {
                    $query->where('type', Transaction::TYPE_INTERNAL_TRANSFER);
                });
            })
                ->where('status', Transaction::STATUS_MATCHING)
                ->when($user->wallet->agency_withdraw_min_amount, function ($builder) use ($user) {
                    $builder->where('amount', '>=', $user->wallet->agency_withdraw_min_amount);
                })
                ->when($user->wallet->agency_withdraw_max_amount, function ($builder) use ($user) {
                    $builder->where('amount', '<=', $user->wallet->agency_withdraw_max_amount);
                })
                ->where('amount', '<=', $account->getRestBalance('withdraw') ?? 0)
                ->whereNull('locked_at')
                ->whereNull('to_id')
                ->where('created_at', '>=', now()->subDay())
                ->oldest()
                ->first();

            if (!$matchingDeposit) {
                continue;
            }
            // 分配出款帳號給代付單
            $transactionFactory->paufenDepositToAccount($account, $matchingDeposit);

            // 用異步Job的方式執行自動代付
            MayaDaifu::dispatch($matchingDeposit, 'init');
        }
    }
}

<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Console\Command;

class CheckMatchingUserLastActivity extends Command
{
    /**
     * @var string
     */
    protected $description = '檢查碼商搶單';
    /**
     * @var string
     */
    protected $signature = 'paufen:check-matching-user-last-activity';

    public function handle()
    {
        $users = User::where('last_activity_at', '<=', now()->subMinutes(1))
            ->with('ancestors')
            ->where('ready_for_matching', true)
            ->get();

        foreach ($users as $user) {
            $ancestors = $user->ancestors->filter(function ($ancestor) use ($user) {
                return $ancestor->control_downline && $ancestor->controlDownlines->pluck('id')->contains($user->id);
            });

            $allOut = $ancestors->every(function ($ancestor) {
                return !$ancestor->ready_for_matching &&
                        Carbon::now()->subMinutes(5)->greaterThan($ancestor->last_activity_at);
            });

            if ($allOut) {
                $user->update([
                    'ready_for_matching' => false
                ]);
            }
        }
    }
}

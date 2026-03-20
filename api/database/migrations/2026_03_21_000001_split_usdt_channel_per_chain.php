<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rename the USDT channel to USDT_TRC20 and update all references.
     */
    public function up(): void
    {
        // 1. Copy the existing USDT channel row to USDT_TRC20
        $usdt = DB::table('channels')->where('code', 'USDT')->first();
        if ($usdt) {
            $data = (array) $usdt;
            $data['code'] = 'USDT_TRC20';
            $data['name'] = 'USDT (TRC-20)';
            $data['updated_at'] = now();
            DB::table('channels')->insert($data);
        }

        // 2. Update all channel_code references from USDT to USDT_TRC20
        DB::table('channel_groups')->where('channel_code', 'USDT')->update(['channel_code' => 'USDT_TRC20']);
        DB::table('channel_amounts')->where('channel_code', 'USDT')->update(['channel_code' => 'USDT_TRC20']);
        DB::table('user_channel_accounts')->where('channel_code', 'USDT')->update(['channel_code' => 'USDT_TRC20']);
        DB::table('transactions')->where('channel_code', 'USDT')->update(['channel_code' => 'USDT_TRC20']);
        DB::table('thirdchannel')->where('channel_code', 'USDT')->update(['channel_code' => 'USDT_TRC20']);
        DB::table('user_tokens')->where('channel_code', 'USDT')->update(['channel_code' => 'USDT_TRC20']);

        // 3. Delete the old USDT channel row
        DB::table('channels')->where('code', 'USDT')->delete();
    }

    /**
     * Reverse: rename USDT_TRC20 back to USDT.
     */
    public function down(): void
    {
        // 1. Copy USDT_TRC20 back to USDT
        $trc20 = DB::table('channels')->where('code', 'USDT_TRC20')->first();
        if ($trc20) {
            $data = (array) $trc20;
            $data['code'] = 'USDT';
            $data['name'] = 'USDT';
            $data['updated_at'] = now();
            DB::table('channels')->insert($data);
        }

        // 2. Revert all channel_code references from USDT_TRC20 to USDT
        DB::table('channel_groups')->where('channel_code', 'USDT_TRC20')->update(['channel_code' => 'USDT']);
        DB::table('channel_amounts')->where('channel_code', 'USDT_TRC20')->update(['channel_code' => 'USDT']);
        DB::table('user_channel_accounts')->where('channel_code', 'USDT_TRC20')->update(['channel_code' => 'USDT']);
        DB::table('transactions')->where('channel_code', 'USDT_TRC20')->update(['channel_code' => 'USDT']);
        DB::table('thirdchannel')->where('channel_code', 'USDT_TRC20')->update(['channel_code' => 'USDT']);
        DB::table('user_tokens')->where('channel_code', 'USDT_TRC20')->update(['channel_code' => 'USDT']);

        // 3. Delete the USDT_TRC20 channel row
        DB::table('channels')->where('code', 'USDT_TRC20')->delete();
    }
};

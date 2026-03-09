<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 先找出 tx_hash 上的 unique index 名稱（可能因建表方式不同而異）
        $indexes = collect(DB::select("SHOW INDEX FROM chain_transactions WHERE Column_name = 'tx_hash' AND Non_unique = 0"));
        $indexNames = $indexes->pluck('Key_name')->unique();

        Schema::table('chain_transactions', function (Blueprint $table) use ($indexNames) {
            foreach ($indexNames as $name) {
                if ($name === 'PRIMARY' || $name === 'chain_tx_hash_account_unique') {
                    continue;
                }
                $table->dropUnique($name);
            }
            $table->unique(['tx_hash', 'user_channel_account_id'], 'chain_tx_hash_account_unique');
        });
    }

    public function down(): void
    {
        Schema::table('chain_transactions', function (Blueprint $table) {
            $table->dropUnique('chain_tx_hash_account_unique');
            $table->unique('tx_hash');
        });
    }
};

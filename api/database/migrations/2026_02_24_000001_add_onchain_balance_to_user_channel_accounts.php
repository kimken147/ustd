<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->decimal('onchain_usdt_balance', 20, 6)->default(0)->after('balance_limit');
            $table->decimal('onchain_trx_balance', 20, 6)->default(0)->after('onchain_usdt_balance');
            $table->timestamp('onchain_synced_at')->nullable()->after('onchain_trx_balance');
        });
    }

    public function down(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropColumn(['onchain_usdt_balance', 'onchain_trx_balance', 'onchain_synced_at']);
        });
    }
};

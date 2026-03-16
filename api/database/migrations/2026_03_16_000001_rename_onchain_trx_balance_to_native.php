<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->renameColumn('onchain_trx_balance', 'onchain_native_balance');
        });
    }

    public function down(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->renameColumn('onchain_native_balance', 'onchain_trx_balance');
        });
    }
};

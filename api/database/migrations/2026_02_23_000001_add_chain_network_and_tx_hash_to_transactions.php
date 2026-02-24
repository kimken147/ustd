<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('chain_network', 10)->nullable()->after('usdt_rate');
            $table->string('tx_hash', 100)->nullable()->index()->after('chain_network');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['chain_network', 'tx_hash']);
        });
    }
};

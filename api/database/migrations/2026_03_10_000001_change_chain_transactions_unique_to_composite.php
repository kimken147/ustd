<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chain_transactions', function (Blueprint $table) {
            $table->dropUnique(['tx_hash']);
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

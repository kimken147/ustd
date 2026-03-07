<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usdt_deposit_monitors', function (Blueprint $table) {
            $table->string('user_tx_hash', 100)->nullable()->after('tx_hash');
        });
    }

    public function down(): void
    {
        Schema::table('usdt_deposit_monitors', function (Blueprint $table) {
            $table->dropColumn('user_tx_hash');
        });
    }
};

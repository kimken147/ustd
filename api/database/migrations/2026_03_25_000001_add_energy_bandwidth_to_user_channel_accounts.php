<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->integer('onchain_energy_available')->nullable()->after('onchain_native_balance');
            $table->integer('onchain_energy_limit')->nullable()->after('onchain_energy_available');
            $table->integer('onchain_bandwidth_available')->nullable()->after('onchain_energy_limit');
            $table->integer('onchain_bandwidth_limit')->nullable()->after('onchain_bandwidth_available');
        });
    }

    public function down(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'onchain_energy_available',
                'onchain_energy_limit',
                'onchain_bandwidth_available',
                'onchain_bandwidth_limit',
            ]);
        });
    }
};

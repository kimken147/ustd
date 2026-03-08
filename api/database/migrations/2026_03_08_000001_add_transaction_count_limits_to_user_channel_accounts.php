<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->unsignedInteger('daily_transaction_count_limit')->nullable()->after('daily_total');
            $table->unsignedInteger('daily_transaction_count_total')->default(0)->after('daily_transaction_count_limit');
            $table->unsignedInteger('withdraw_daily_transaction_count_limit')->nullable()->after('withdraw_daily_total');
            $table->unsignedInteger('withdraw_daily_transaction_count_total')->default(0)->after('withdraw_daily_transaction_count_limit');
            $table->unsignedInteger('monthly_transaction_count_limit')->nullable()->after('monthly_total');
            $table->unsignedInteger('monthly_transaction_count_total')->default(0)->after('monthly_transaction_count_limit');
            $table->unsignedInteger('withdraw_monthly_transaction_count_limit')->nullable()->after('withdraw_monthly_total');
            $table->unsignedInteger('withdraw_monthly_transaction_count_total')->default(0)->after('withdraw_monthly_transaction_count_limit');
        });
    }

    public function down(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'daily_transaction_count_limit',
                'daily_transaction_count_total',
                'withdraw_daily_transaction_count_limit',
                'withdraw_daily_transaction_count_total',
                'monthly_transaction_count_limit',
                'monthly_transaction_count_total',
                'withdraw_monthly_transaction_count_limit',
                'withdraw_monthly_transaction_count_total',
            ]);
        });
    }
};

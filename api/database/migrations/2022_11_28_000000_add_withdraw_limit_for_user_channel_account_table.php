<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWithdrawLimitForUserChannelAccountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->decimal('balance_limit', 14, 2)->nullable()->after('balance');
            $table->decimal('withdraw_daily_limit', 14, 2)->nullable()->after('daily_total');
            $table->decimal('withdraw_daily_total', 14, 2)->default(0)->after('withdraw_daily_limit');
            $table->decimal('withdraw_monthly_limit', 15, 2)->nullable()->after('monthly_total');
            $table->decimal('withdraw_monthly_total', 15, 2)->default(0)->after('withdraw_monthly_limit');
            $table->boolean('is_auto')->default(true)->after('type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropColumn('balance_limit');
            $table->dropColumn('withdraw_daily_limit');
            $table->dropColumn('withdraw_daily_total');
            $table->dropColumn('withdraw_monthly_limit');
            $table->dropColumn('withdraw_monthly_total');
            $table->dropColumn('is_auto');
        });

    }
}

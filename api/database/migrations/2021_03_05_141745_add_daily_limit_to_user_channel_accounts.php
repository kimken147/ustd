<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDailyLimitToUserChannelAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->tinyInteger('daily_status')->default('1')->after('fee_percent');
            $table->decimal('daily_limit', 10, 2)->nullable()->after('daily_status');
            $table->decimal('daily_total', 10, 2)->default('0.00')->after('daily_limit');
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
            $table->dropColumn('daily_status');
            $table->dropColumn('daily_limit');
            $table->dropColumn('daily_total');
        });
    }
}

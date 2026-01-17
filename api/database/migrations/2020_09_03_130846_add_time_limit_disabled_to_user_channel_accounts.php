<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTimeLimitDisabledToUserChannelAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->boolean('time_limit_disabled')->after('regular_customer_first')->default(false);
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
            $table->dropColumn('time_limit_disabled');
        });
    }
}

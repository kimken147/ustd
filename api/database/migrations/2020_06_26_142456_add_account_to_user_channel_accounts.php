<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccountToUserChannelAccounts extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropUnique(['channel_amount_id', 'device_id']);
            $table->string('account')->after('status');
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
            $table->unique(['channel_amount_id', 'device_id']);
            $table->dropColumn('account');
        });
    }
}

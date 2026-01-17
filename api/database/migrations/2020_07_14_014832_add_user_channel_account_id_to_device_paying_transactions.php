<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserChannelAccountIdToDevicePayingTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('device_paying_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_channel_account_id')->after('device_id');
            $table->unique(['user_channel_account_id', 'transaction_id'], 'uca_t_unique');
            $table->dropUnique(['device_id', 'transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('device_paying_transactions', function (Blueprint $table) {
            $table->dropUnique('uca_t_unique');
            $table->dropColumn('user_channel_account_id');
            $table->unique(['device_id', 'transaction_id']);
        });
    }
}

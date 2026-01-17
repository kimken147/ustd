<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReworkUserChannelAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropUnique(['user_channel_id', 'device_id']);

            $table->dropColumn('user_channel_id');
            $table->dropColumn('type');
            $table->unsignedBigInteger('user_id')->after('id');
            $table->unsignedBigInteger('channel_amount_id')->after('user_id');

            $table->unique(['channel_amount_id', 'device_id']);
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
            $table->dropUnique(['channel_amount_id', 'device_id']);

            $table->dropColumn('channel_amount_id');
            $table->dropColumn('user_id');
            $table->unsignedBigInteger('user_channel_id')->after('id');
            $table->unsignedTinyInteger('type')->after('device_id');

            $table->unique(['user_channel_id', 'device_id']);
        });
    }
}

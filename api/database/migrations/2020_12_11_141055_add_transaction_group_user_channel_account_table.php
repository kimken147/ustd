<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransactionGroupUserChannelAccountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_group_user_channel_account', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_group_id');
            $table->unsignedBigInteger('user_channel_account_id');

            $table->unique(['user_channel_account_id', 'transaction_group_id'], 'uua_tg_unique');
            $table->unique(['transaction_group_id', 'user_channel_account_id'], 'tg_uua_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_group_user_channel_account');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterDecimalForVn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('system_bank_cards', function (Blueprint $table) {
            $table->decimal('balance', 14, 2)->change();
        });

        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->decimal('balance', 14, 2)->change();
            $table->decimal('notify_balance', 14, 2)->change();
        });

        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->decimal('balance', 14, 2)->change();
            $table->decimal('daily_limit', 14, 2)->change();
            $table->decimal('daily_total', 14, 2)->change();
            $table->decimal('monthly_limit', 15, 2)->change();
            $table->decimal('monthly_total', 15, 2)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('balance_limit', 14, 2)->change();
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('balance', 14, 2)->change();
            $table->decimal('frozen_balance', 14, 2)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}

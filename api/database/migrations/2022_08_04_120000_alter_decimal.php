<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterDecimal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_amounts', function (Blueprint $table) {
            $table->decimal('min_amount', 10, 2)->change();
        });

        Schema::table('device_paying_transactions', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
        });

        Schema::table('matching_deposit_rewards', function (Blueprint $table) {
            $table->decimal('min_amount', 10, 2)->change();
            $table->decimal('max_amount', 12, 2)->change();
            $table->decimal('reward_amount', 10, 2)->change();
        });

        Schema::table('merchant_thirdchannel', function (Blueprint $table) {
            $table->decimal('withdraw_fee', 10, 2)->change();
            $table->decimal('daifu_max', 12, 2)->change();
            $table->decimal('deposit_max', 12, 2)->change();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->decimal('need', 12, 2)->change();
            $table->decimal('but', 12, 2)->change();
        });

        Schema::table('system_bank_cards', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->change();
        });

        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->change();
            $table->decimal('notify_balance', 10, 2)->change();
            $table->decimal('auto_daifu_threshold', 12, 2)->change();
        });

        Schema::table('transaction_rewards', function (Blueprint $table) {
            $table->decimal('min_amount', 10, 2)->change();
            $table->decimal('max_amount', 12, 2)->change();
            $table->decimal('reward_amount', 10, 2)->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
            $table->decimal('floating_amount', 12, 2)->change();
            $table->decimal('actual_amount', 12, 2)->change();
        });

        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->change();
            $table->decimal('min_amount', 10, 2)->change();
            $table->decimal('max_amount', 12, 2)->change();
            $table->decimal('daily_limit', 12, 2)->change();
            $table->decimal('daily_total', 12, 2)->change();
        });

        Schema::table('user_channels', function (Blueprint $table) {
            $table->decimal('min_amount', 10, 2)->change();
            $table->decimal('max_amount', 12, 2)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->decimal('balance_limit', 12, 2)->change();
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('balance', 12, 2)->change();
            $table->decimal('profit', 12, 2)->change();
            $table->decimal('frozen_balance', 12, 2)->change();
            $table->decimal('withdraw_fee', 10, 2)->change();
            $table->decimal('withdraw_profit_fee', 12, 2)->change();
            $table->decimal('agency_withdraw_fee', 12, 2)->change();
            $table->decimal('withdraw_min_amount', 10, 2)->change();
            $table->decimal('withdraw_max_amount', 12, 2)->change();
            $table->decimal('withdraw_profit_min_amount', 10, 2)->change();
            $table->decimal('withdraw_profit_max_amount', 12, 2)->change();
            $table->decimal('agency_withdraw_min_amount', 10, 2)->change();
            $table->decimal('agency_withdraw_max_amount', 12, 2)->change();
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

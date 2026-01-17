<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWithdrawProfitEnableToUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('withdraw_profit_enable')->default(false)->after('withdraw_enable');
        });
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('withdraw_profit_fee', 8, 2)->after('withdraw_fee')->default(0);
            $table->decimal('withdraw_profit_min_amount', 8, 2)->after('withdraw_max_amount')->nullable();
            $table->decimal('withdraw_profit_max_amount', 8, 2)->after('withdraw_profit_min_amount')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('withdraw_profit_enable');
        });
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('withdraw_profit_fee');
            $table->dropColumn('withdraw_profit_min_amount');
            $table->dropColumn('withdraw_profit_max_amount');
        });
    }
}

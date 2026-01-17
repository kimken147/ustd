<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAmountLimitToWallets extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->unsignedDecimal('withdraw_min_amount', 8, 2)->after('agency_withdraw_fee')->nullable();
            $table->unsignedDecimal('withdraw_max_amount', 8, 2)->after('withdraw_min_amount')->nullable();
            $table->unsignedDecimal('agency_withdraw_min_amount', 8, 2)->after('withdraw_max_amount')->nullable();
            $table->unsignedDecimal('agency_withdraw_max_amount', 8, 2)->after('agency_withdraw_min_amount')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('withdraw_min_amount');
            $table->dropColumn('withdraw_max_amount');
            $table->dropColumn('agency_withdraw_min_amount');
            $table->dropColumn('agency_withdraw_max_amount');
        });
    }
}

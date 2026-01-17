<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAgencyWithdrawFeeForDollar extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('agency_withdraw_fee', 5, 2)->nullable()->change();
            $table->decimal('agency_withdraw_fee_dollar', 5, 2)->after('agency_withdraw_fee')->nullable();
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
            $table->decimal('agency_withdraw_fee', 5, 2)->nullable(false)->change();
            $table->dropColumn('agency_withdraw_fee_dollar');
        });
    }
}

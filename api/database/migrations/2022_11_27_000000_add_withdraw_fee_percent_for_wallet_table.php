<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWithdrawFeePercentForWalletTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('withdraw_fee_percent', 5, 2)->default(0)->after('withdraw_fee');
            $table->decimal('additional_withdraw_fee', 5, 2)->default(0)->after('withdraw_fee_percent');
            $table->decimal('additional_agency_withdraw_fee', 5, 2)->default(0)->after('agency_withdraw_fee_dollar');
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
            $table->dropColumn('withdraw_fee_percent');
            $table->dropColumn('additional_withdraw_fee');
            $table->dropColumn('additional_agency_withdraw_fee');
        });

    }
}

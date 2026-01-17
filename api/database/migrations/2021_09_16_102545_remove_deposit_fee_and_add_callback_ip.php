<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveDepositFeeAndAddCallbackIp extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->dropColumn('deposit_fee');
            $table->char('white_ip', 255)->nullable()->after('status');
            $table->renameColumn('deposit_fee_per', 'transaction_fee_percent');
            $table->renameColumn('daifu_fee_per', 'daifu_fee_percent');
            $table->renameColumn('daifu_fee', 'withdraw_fee');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->unsignedDecimal('deposit_fee', 4, 2)->default(0);
            $table->dropColumn('white_ip');
            $table->renameColumn('transaction_fee_percent', 'deposit_fee_per');
            $table->renameColumn('daifu_fee_percent', 'daifu_fee_per');
            $table->renameColumn('withdraw_fee', 'daifu_fee');
        });
    }
}

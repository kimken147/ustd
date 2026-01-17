<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ThirdchannelDataAdjustment extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $thirdchannels = DB::table('thirdchannel')->get();
        $merchantThirdchannels = DB::table('merchant_thirdchannel')->get();

        foreach ($thirdchannels as $thirdchannel) {
            DB::table('merchant_thirdchannel')->where('thirdchannel_id', $thirdchannel->id)
                ->update([
                    'deposit_fee_percent' => $thirdchannel->transaction_fee_percent,
                    'withdraw_fee' => $thirdchannel->withdraw_fee,
                    'daifu_fee_percent' => $thirdchannel->daifu_fee_percent,
                ]);
        }

        foreach ($merchantThirdchannels as $merchantThirdchannel) {
            DB::table('thirdchannel')->where('id', $merchantThirdchannel->thirdchannel_id)
                ->update([
                    'merchant_id' => $merchantThirdchannel->merchant_number,
                    'key' => $merchantThirdchannel->key,
                    'key2' => $merchantThirdchannel->key2,
                    'key3' => $merchantThirdchannel->key3,
                ]);
        }

        DB::table('thirdchannel')->where('class', 'Zuanshi')->update(['class' => 'YFHL']);

        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->dropColumn('transaction_fee_percent');
            $table->dropColumn('withdraw_fee');
            $table->dropColumn('daifu_fee_percent');
        });

        Schema::table('merchant_thirdchannel', function (Blueprint $table) {
            $table->dropColumn('merchant_number');
            $table->dropColumn('key');
            $table->dropColumn('key2');
            $table->dropColumn('key3');
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

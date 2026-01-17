<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMinMaxToMerchantThirdChannel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('merchant_thirdchannel', function (Blueprint $table) {
            $table->unsignedDecimal('deposit_max', 10, 2)->default(0)->after('key3');
            $table->unsignedDecimal('deposit_min', 10, 2)->default(0)->after('key3');
            $table->unsignedDecimal('daifu_max', 10, 2)->default(0)->after('key3');
            $table->unsignedDecimal('daifu_min', 10, 2)->default(0)->after('key3');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('merchant_thirdchannel', function (Blueprint $table) {
            $table->dropColumn('deposit_max');
            $table->dropColumn('deposit_min');
            $table->dropColumn('daifu_max');
            $table->dropColumn('daifu_min');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ThirdchannelAddColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->decimal('balance', 10, 2)->after('custom_url')->default(0);
            $table->char('merchant_id', 255)->after('balance')->nullable();
            $table->text('key')->after('merchant_id')->nullable();
            $table->text('key2')->after('key')->nullable();
            $table->text('key3')->after('key2')->nullable();
        });

        Schema::table('merchant_thirdchannel', function (Blueprint $table) {
            $table->unsignedDecimal('deposit_fee_percent', 5, 2)->default(0)->after('key3');
            $table->unsignedDecimal('withdraw_fee', 5, 2)->default(0)->after('deposit_fee_percent');
            $table->unsignedDecimal('daifu_fee_percent', 5, 2)->default(0)->after('withdraw_fee');
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
            $table->dropColumn('balance');
            $table->dropColumn('merchant_id');
            $table->dropColumn('key');
            $table->dropColumn('key2');
            $table->dropColumn('key3');
        });

        Schema::table('merchant_thirdchannel', function (Blueprint $table) {
            $table->dropColumn('deposit_fee_percent');
            $table->dropColumn('withdraw_fee');
            $table->dropColumn('daifu_fee_percent');
        });
    }
}

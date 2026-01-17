<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUseThirdCashierUrlToThirdchannelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->boolean('use_third_cashier_url')
                ->default(false)
                ->after('enable_system_order_number')
                ->comment('使用第三方收銀台網址');
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
            $table->dropColumn('use_third_cashier_url');
        });
    }
}

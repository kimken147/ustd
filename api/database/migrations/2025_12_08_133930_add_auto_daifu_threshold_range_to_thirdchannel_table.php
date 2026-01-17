<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAutoDaifuThresholdRangeToThirdchannelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            // 新增最小金额字段
            $table->decimal('auto_daifu_threshold_min', 12, 2)
                ->default(0.00)
                ->after('auto_daifu_threshold')
                ->comment('代付自动审核最小金额');
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
            $table->dropColumn('auto_daifu_threshold_min');
        });
    }
}

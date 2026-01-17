<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RefectorThirdChannelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->text('custom_url')->after('status');
        });

        Schema::table('merchant_thirdchannel', function (Blueprint $table) {
            $table->dropColumn('custom_url');
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
            $table->dropColumn('custom_url');
        });

        Schema::table('merchant_thirdchannel', function (Blueprint $table) {
            $table->text('custom_url')->after('thirdchannel_id');
        });
    }
}

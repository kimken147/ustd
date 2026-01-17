<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateThirdChannelPermissionsName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('permissions')
        ->where('name', '四方通道设定')
        ->update([
            "group_name" => "三方管理",
            "name" => "三方通道设定"
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')
        ->where('name', '三方通道设定')
        ->update([
            "group_name" => "商户管理",
            "name" => "四方通道设定"
        ]);
    }
}

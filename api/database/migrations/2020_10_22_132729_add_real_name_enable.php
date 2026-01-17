<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRealNameEnable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->boolean('real_name_enable')->default(false)->after('floating_enable');
        });

        Schema::table('user_channels', function (Blueprint $table) {
            $table->boolean('real_name_enable')->default(false)->after('floating_enable');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('real_name_enable');
        });

        Schema::table('user_channels', function (Blueprint $table) {
            $table->dropColumn('real_name_enable');
        });
    }
}

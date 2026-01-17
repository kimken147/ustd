<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaufenSwitchesToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('paufen_deposit_enable')->default(false)->after('deposit_enable');
            $table->boolean('paufen_withdraw_enable')->default(false)->after('withdraw_enable');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('paufen_deposit_enable');
            $table->dropColumn('paufen_withdraw_enable');
        });
    }
}

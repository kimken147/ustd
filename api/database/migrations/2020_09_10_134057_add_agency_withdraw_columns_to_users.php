<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAgencyWithdrawColumnsToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('agency_withdraw_enable')->after('paufen_withdraw_enable')->default(false);
            $table->boolean('paufen_agency_withdraw_enable')->after('agency_withdraw_enable')->default(false);
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
            $table->dropColumn('agency_withdraw_enable');
            $table->dropColumn('paufen_agency_withdraw_enable');
        });
    }
}

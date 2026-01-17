<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBankIdToTimeLimitBanks extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('time_limit_banks', function (Blueprint $table) {
            $table->unsignedBigInteger('bank_id')->default('0')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('time_limit_banks', function (Blueprint $table) {
            $table->dropColumn('bank_id');
        });
    }
}

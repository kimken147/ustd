<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FillValueInPersonalEnable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('transaction_groups')
        ->where('transaction_type', 1)
        ->update([
            'personal_enable'   => true,
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('transaction_groups')
        ->where('transaction_type', 1)
        ->update([
            'personal_enable'   => false,
        ]);
    }
}

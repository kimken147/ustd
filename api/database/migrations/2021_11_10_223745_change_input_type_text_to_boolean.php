<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeInputTypeTextToBoolean extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $idArray = [6,8,9,10,11,12,14,18,19,20,21,23,25,27,28,32,37,39,40,42];
        DB::table('feature_toggles')
        ->whereIn('id', $idArray)
        ->update([
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $idArray = [6,8,9,10,11,12,14,18,19,20,21,23,25,27,28,32,37,39,40,42];
        DB::table('feature_toggles')
        ->whereIn('id', $idArray)
        ->update([
            'input'   => [
                'type'  => 'text',
                'value' => '0'
            ],
        ]);
    }
}

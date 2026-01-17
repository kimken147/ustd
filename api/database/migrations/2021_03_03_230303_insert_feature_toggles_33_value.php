<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class InsertFeatureToggles33Value extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('feature_toggles')->insert([
                'id'         => 33,
                'hidden'     => 0,
                'enabled'    => 0,
                'input'      => '{"type": "text", "value": "0.1"}',
                'created_at' => DB::raw('NOW()'),
                'updated_at' => DB::raw('NOW()'),
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('feature_toggles')->where('id',33)->delete();
    }
}

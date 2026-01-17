<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\FeatureToggle;

class EditId40 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('feature_toggles')
        ->where('id', 40)
        ->update([
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '0'
            ],
        ]);

        $toggle = FeatureToggle::find(33);
        $toggle->input = [
            'type'  => 'text',
            'value' => $toggle->input['value'],
            'unit'  => ''
        ];
        $toggle->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\FeatureToggle;

class AddUnitToInputColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $units = ['元', '秒', '人', '秒', '%', '', '元', '', '', '', '', '', '元', '', '笔', '元', '', '', '', '', '', '笔', '', '秒', '', '次', '', '', '笔', '元', '', '', '', '', '元', '笔', '', '笔', '', '笔', '天', ''];

        foreach ($units as $idx => $unit) {
            $toggle = FeatureToggle::find($idx + 1);
            if (!$toggle) {
                continue;
            }
            $toggle->input = [
                'type'  => $unit == '' ? 'boolean' : 'text',
                'value' => $toggle->input['value'] ?? 0,
                'unit'  => $unit
            ];
            $toggle->save();
        }
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

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateThirdChannelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('thirdchannel', function (Blueprint $table) {
            $table->id();
            $table->char('name', 255);
            $table->char('class', 255);
            $table->unsignedTinyInteger('type')->default(1);
            $table->boolean('status')->default(true);
            $table->unsignedDecimal('deposit_fee', 4, 2)->default(0);
            $table->unsignedDecimal('deposit_fee_per', 4, 2)->default(0);
            $table->unsignedDecimal('daifu_fee', 4, 2)->default(0);
            $table->unsignedDecimal('daifu_fee_per', 4, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('thirdchannel');
    }
}

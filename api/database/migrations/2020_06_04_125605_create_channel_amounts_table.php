<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChannelAmountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('channel_amounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('channel_group_id');
            $table->char('channel_code', 20);
            $table->unsignedDecimal('min_amount', 8, 2);
            $table->unsignedDecimal('max_amount', 8, 2);
            $table->timestamps();

            $table->unique(['channel_group_id', 'min_amount', 'max_amount']);
            $table->unique(['channel_code', 'min_amount', 'max_amount']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('channel_amounts');
    }
}

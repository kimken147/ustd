<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMerchantThirdChannelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('merchant_thirdchannel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('thirdchannel_id');
            $table->char('custom_url', 255)->nullable();
            $table->char('merchant_number', 255)->nullable();
            $table->char('key', 255)->nullable();
            $table->char('key2', 255)->nullable();
            $table->char('key3', 255)->nullable();
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
        Schema::dropIfExists('merchant_thirdchannel');
    }
}
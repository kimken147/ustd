<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChannelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->char('code', 20);
            $table->string('name', 20);
            $table->boolean('status')->default(0);
            $table->unsignedSmallInteger('order_timeout');
            $table->boolean('order_timeout_enable')->default(1);
            $table->unsignedSmallInteger('transaction_timeout');
            $table->boolean('transaction_timeout_enable')->default(1);
            $table->tinyInteger('floating');
            $table->boolean('floating_enable')->default(0);
            $table->unsignedTinyInteger('present_result');

            $table->primary('code');
            $table->timestamps();
        });

        Schema::create('user_channels', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('user_id');
            $table->char('channel_code', 20);
            $table->boolean('status')->default(1);

            $table->unsignedDecimal('min_amount', 8, 2);
            $table->unsignedDecimal('max_amount', 8, 2);
            $table->unsignedDecimal('fee_percent', 4, 2);
            $table->boolean('floating_enable')->default(0);
            $table->char('note', 50)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'channel_code', 'min_amount', 'max_amount']);
        });

        Schema::create('user_channel_accounts', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('user_channel_id');
            $table->unsignedTinyInteger('type');
            $table->unsignedTinyInteger('status');
            $table->json('detail');

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
        Schema::dropIfExists('channels');
        Schema::dropIfExists('user_channels');
        Schema::dropIfExists('user_channel_accounts');
    }
}

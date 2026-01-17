<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionRewardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedDecimal('min_amount', 8, 2);
            $table->unsignedDecimal('max_amount', 8, 2);
            $table->unsignedDecimal('reward_amount', 8, 2);
            $table->unsignedTinyInteger('reward_unit');
            $table->time('started_at');
            $table->time('ended_at');
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
        Schema::dropIfExists('transaction_rewards');
    }
}

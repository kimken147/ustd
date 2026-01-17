<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMatchingDepositRewardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('matching_deposit_rewards', function (Blueprint $table) {
            $table->id();
            $table->unsignedDecimal('min_amount', 8, 2);
            $table->unsignedDecimal('max_amount', 8, 2);
            $table->unsignedDecimal('reward_amount', 8, 2);
            $table->unsignedTinyInteger('reward_unit');
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
        Schema::dropIfExists('matching_deposit_rewards');
    }
}

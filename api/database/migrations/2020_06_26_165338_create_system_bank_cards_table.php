<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSystemBankCardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('system_bank_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('status')->default(0);
            $table->unsignedDecimal('balance', 8, 2)->default(0);
            $table->string('bank_card_holder_name');
            $table->string('bank_card_number');
            $table->string('bank_name');
            $table->timestamps();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_matched_at')->nullable();
        });

        Schema::create('system_bank_card_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('system_bank_card_id');
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
        Schema::dropIfExists('system_bank_cards');
    }
}

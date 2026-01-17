<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserTokens extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create("user_tokens", function (Blueprint $table) {
            $table->id();
            $table->string("username");
            $table->string("password");
            $table->char("channel_code", 20);
            $table->string("token1")->nullable();
            $table->string("token2")->nullable();
            $table->string("token3")->nullable();
            $table->timestamp("created_at")->useCurrent();
            $table->timestamp("updated_at");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists("user_tokens");
    }
}

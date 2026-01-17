<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class CreateUserChannelAccountAuditsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_channel_account_audits', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('user_channel_account_id');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('updated_by')->nullable();
            $table->timestamps();

            $table->index(['user_channel_account_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_channel_account_audits');
    }
}

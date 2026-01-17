<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBannedRealNamesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banned_realnames', function (Blueprint $table) {
            $table->id();
            $table->char('realname', 255);
            $table->unsignedTinyInteger('type')->default(1);
            $table->timestamps();

            $table->unique(['realname', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('banned_realnames');
    }
}

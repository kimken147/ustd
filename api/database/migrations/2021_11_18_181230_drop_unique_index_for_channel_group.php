<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DropUniqueIndexForChannelGroup extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_amounts', function (Blueprint $table) {
            $table->dropUnique(['channel_group_id', 'min_amount', 'max_amount']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_amounts', function (Blueprint $table) {
            $table->unique(['channel_group_id', 'min_amount', 'max_amount']);
        });
    }
}

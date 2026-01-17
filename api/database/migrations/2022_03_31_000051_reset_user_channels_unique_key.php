<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ResetUserChannelsUniqueKey extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_amounts', function (Blueprint $table) {
            $table->dropUnique(['channel_code', 'min_amount', 'max_amount']);
            $table->unique(['channel_code', 'min_amount', 'max_amount', 'deleted_at'], 'channel_min_max_amount_deleted_at');
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
            $table->dropUnique('channel_min_max_amount_deleted_at');
            $table->unique(['channel_code', 'min_amount', 'max_amount']);
        });
    }
}

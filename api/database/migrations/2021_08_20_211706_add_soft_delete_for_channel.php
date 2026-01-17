<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeleteForChannel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_amounts', function (Blueprint $table) {
            $table->softDeletes($column = 'deleted_at');
        });
        Schema::table('channel_groups', function (Blueprint $table) {
            $table->softDeletes($column = 'deleted_at');
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
            $table->dropColumn('deleted_at');
        });
        Schema::table('channel_groups', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNote extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('banned_ips', function (Blueprint $table) {
            $table->string('note', 50)->after('type')->nullable();
        });
        Schema::table('banned_realnames', function (Blueprint $table) {
            $table->string('note', 50)->after('type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('banned_ips', function (Blueprint $table) {
            $table->dropColumn('note');
        });
        Schema::table('banned_realnames', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
}

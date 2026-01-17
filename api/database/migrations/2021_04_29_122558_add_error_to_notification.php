<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddErrorToNotification extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->integer('error')->default('0')->after('notification')->nullable();
            $table->decimal('need',10,2)->default('0.00')->after('error')->nullable();
            $table->decimal('but',10,2)->default('0.00')->after('need')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('error');
            $table->dropColumn('need');
            $table->dropColumn('but');
        });
    }
}

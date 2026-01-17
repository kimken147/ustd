<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddToAndIndexForAnnoucementTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->index(['started_at', 'ended_at']);
            $table->dropColumn('target');
            $table->boolean('for_merchant')->default(0)->after('notes');
            $table->boolean('for_provider')->default(0)->after('for_merchant');
        });

        Schema::create('announcement_users', function (Blueprint $table) {
            $table->integer('announcement_id');
            $table->bigInteger('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->tinyInteger('target')->unsigned()->default(1);
            $table->dropIndex(['started_at', 'ended_at']);
            $table->dropColumn('for_merchant');
            $table->dropColumn('for_provider');
        });

        Schema::dropIfExists('announcement_users');
    }
}

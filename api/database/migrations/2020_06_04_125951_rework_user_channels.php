<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ReworkUserChannels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channels', function (Blueprint $table) {
            $table->dropUnique(['user_id','channel_code','min_amount','max_amount']);
            $table->dropColumn(['min_amount', 'max_amount', 'channel_code', 'note']);
            $table->unsignedBigInteger('channel_group_id')->after('user_id');

            $table->unique(['user_id', 'channel_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_channels', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'channel_group_id']);
            $table->dropColumn('channel_group_id');

            $table->char('channel_code', 20)->after('user_id');
            $table->unsignedDecimal('min_amount', 8, 2)->after('status');
            $table->unsignedDecimal('max_amount', 8, 2)->after('min_amount');
            $table->char('note', 50)->nullable()->after('floating_enable');

            $table->unique(['user_id', 'channel_code', 'min_amount', 'max_amount']);
        });
    }
}

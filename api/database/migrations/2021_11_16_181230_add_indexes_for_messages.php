<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIndexesForMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEXES FROM messages"))->pluck('Key_name')->contains('messages_to_id_index')) {
                $table->index('to_id');
            }
        });
        Schema::table('notifications', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEXES FROM notifications"))->pluck('Key_name')->contains('notifications_transaction_id_index')) {
                $table->index('transaction_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            if (collect(DB::select("SHOW INDEXES FROM messages"))->pluck('Key_name')->contains('messages_to_id_index')) {
                $table->dropIndex('messages_to_id_index');
            }
        });
        Schema::table('notifications', function (Blueprint $table) {
            if (collect(DB::select("SHOW INDEXES FROM notifications"))->pluck('Key_name')->contains('notifications_transaction_id_index')) {
                $table->dropIndex('notifications_transaction_id_index');
            }
        });
    }
}

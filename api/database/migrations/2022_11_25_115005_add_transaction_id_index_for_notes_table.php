<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransactionIdIndexForNotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_notes', function (Blueprint $table) {
            $table->index(['transaction_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['to_channel_account_id', 'status']);
        });

        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->index(['channel_code', 'status', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_notes', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['to_channel_account_id', 'status']);
        });

        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropIndex(['channel_code', 'status', 'type']);
        });
    }
}

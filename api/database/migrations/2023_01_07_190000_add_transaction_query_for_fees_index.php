<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddTransactionQueryForFeesIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_fees', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEXES FROM transaction_fees"))->pluck('Key_name')->contains('transaction_query_index')) {
                $table->index(['transaction_id', 'user_id', 'thirdchannel_id', 'actual_fee', 'actual_profit'], 'transaction_query_index');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_confirmed_at_type_status_index')) {
                $table->dropIndex('transactions_confirmed_at_type_status_index');
            }
            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_created_at_type_status_index')) {
                $table->dropIndex('transactions_created_at_type_status_index');
            }
            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transaction_id_status_type_created_at_confirmed_at_index')) {
                $table->index(['id', 'status', 'type', 'created_at', 'confirmed_at']);
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

    }
}


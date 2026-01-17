<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class RemoveTransactionsQueryIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_status_type_created_at_confirmed_at_index')) {
                $table->dropIndex('transactions_status_type_created_at_confirmed_at_index');
            }
            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_client_ipv4_status_created_at_index') &&
                !collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_client_ipv4_status_created_at')) {
                $table->index(['client_ipv4', 'status', 'created_at']);
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


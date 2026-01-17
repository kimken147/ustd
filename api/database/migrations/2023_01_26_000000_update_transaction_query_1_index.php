<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class UpdateTransactionQuery1Index extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_query_1')) {
                $table->dropIndex('transactions_query_1');
                $table->index(['id', 'status', 'type', 'floating_amount', 'order_number', 'created_at', 'confirmed_at'], 'transactions_query_1');
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


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddTransactionsFromIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_query_2')) {
                $table->index(['from_channel_account_id', 'status', 'amount', 'floating_amount', 'created_at'], 'transactions_query_2');
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


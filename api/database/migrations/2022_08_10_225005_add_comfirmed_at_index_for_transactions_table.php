<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddComfirmedAtIndexForTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_confirmed_at_from_id_idx')) {
                $table->index(['confirmed_at', 'from_id']);
            }

            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_confirmed_at_to_id_idx')) {
                $table->index(['confirmed_at', 'to_id']);
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


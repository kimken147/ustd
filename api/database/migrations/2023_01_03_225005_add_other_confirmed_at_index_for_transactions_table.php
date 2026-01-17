<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddOtherConfirmedAtIndexForTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_confirmed_at_type_status_index')) {
                $table->index(['confirmed_at', 'type', 'status']);
            }
            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_created_at_type_status_index')) {
                $table->index(['created_at', 'type', 'status']);
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


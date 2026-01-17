<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class RemoveTransactionsQueryIpIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_client_ipv4_status_created_at_index')) {
                $table->dropIndex('transactions_client_ipv4_status_created_at_index');
            }
            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_type_from_id_created_at_floating_amount_id_index')) {
                $table->dropIndex('transactions_type_from_id_created_at_floating_amount_id_index');
            }
            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_query_1')) {
                $table->dropIndex('transactions_query_1');
                $table->index(['id', 'from_id', 'to_id', 'status', 'type', 'floating_amount', 'order_number', 'created_at', 'confirmed_at'], 'transactions_query_1');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('cashier_title', 255)->default('')->after('tags');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cashier_title');
        });
    }
}


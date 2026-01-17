<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddIndexesForTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_type_from_id_created_at_floating_amount_id_index')) {
                $table->index(['type', 'from_id', 'created_at', 'floating_amount', 'id']);
            }

            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_type_status_created_at_index')) {
                $table->index(['type', 'status', 'created_at']);
            }

            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_parent_id_index')) {
                $table->index(['parent_id']);
            }

            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_type_created_at_deleted_at_index')) {
                $table->index(['type', 'created_at', 'deleted_at']);
            }
        });
        Schema::table('transaction_fees', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEXES FROM transaction_fees"))->pluck('Key_name')->contains('transaction_fees_tid_uid_am_ap_index')) {
                $table->index(['transaction_id', 'user_id', 'account_mode', 'actual_profit'], 'transaction_fees_tid_uid_am_ap_index');
            }
        });
        Schema::table('transaction_certificate_files', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEXES FROM transaction_certificate_files"))->pluck('Key_name')->contains('transaction_certificate_files_transaction_id_id_index')) {
                $table->index(['transaction_id', 'id']);
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
        Schema::table('transactions', function (Blueprint $table) {
            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_type_from_id_created_at_floating_amount_id_index')) {
                $table->dropIndex('transactions_type_from_id_created_at_floating_amount_id_index');
            }

            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_type_status_created_at_index')) {
                $table->dropIndex('transactions_type_status_created_at_index');
            }

            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_parent_id_index')) {
                $table->dropIndex('transactions_parent_id_index');
            }

            if (collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('transactions_type_created_at_deleted_at_index')) {
                $table->dropIndex('transactions_type_created_at_deleted_at_index');
            }
        });
        Schema::table('transaction_fees', function (Blueprint $table) {
            if (collect(DB::select("SHOW INDEXES FROM transaction_fees"))->pluck('Key_name')->contains('transaction_fees_tid_uid_am_ap_index')) {
                $table->dropIndex('transaction_fees_tid_uid_am_ap_index');
            }
        });
        Schema::table('transaction_certificate_files', function (Blueprint $table) {
            if (collect(DB::select("SHOW INDEXES FROM transaction_certificate_files"))->pluck('Key_name')->contains('transaction_certificate_files_transaction_id_id_index')) {
                $table->dropIndex('transaction_certificate_files_transaction_id_id_index');
            }
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddFromChannelAccountToTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('_from_channel_account', 50)->default('')->after('deleted_at');

            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('type_order_number_created_confirmed_at')) {
                $table->index(['type', 'order_number', 'created_at', 'confirmed_at'], 'type_order_number_created_confirmed_at');
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            DB::statement('UPDATE transactions AS t 
                    LEFT JOIN user_channel_accounts AS u ON t.from_channel_account_id = u.id
                    SET t._from_channel_account = u.account 
                    WHERE t.from_channel_account_id IS NOT NULL
                    AND t.created_at >= "2023-01-01 00:00:00"');

            if (!collect(DB::select("SHOW INDEXES FROM transactions"))->pluck('Key_name')->contains('type__from_channel_account_created_confirmed_at')) {
                $table->index(['type', '_from_channel_account', 'created_at', 'confirmed_at'], 'type__from_channel_account_created_confirmed_at');
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
            $table->dropColumn('_from_channel_account');
        });
    }
}


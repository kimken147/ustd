<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeletesToTables extends Migration
{

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        foreach ([
                     'users', 'wallets', 'wallet_histories', 'transactions', 'transaction_fees'
                 ] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ([
                     'users', 'wallets', 'wallet_histories', 'transactions', 'transaction_fees'
                 ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if ($tableName === 'transaction_fees') {
                    $table->softDeletes();
                } else {
                    $table->softDeletes()->after('updated_at');
                }
            });
        }
    }
}

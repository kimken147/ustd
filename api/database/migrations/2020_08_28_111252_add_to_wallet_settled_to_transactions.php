<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddToWalletSettledToTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('to_wallet_settled')->default(false)->after('to_account_mode');
            $table->timestamp('to_wallet_should_settled_at')->nullable()->after('operated_at');
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
            $table->dropColumn('to_wallet_settled');
            $table->dropColumn('to_wallet_should_settled_at');
        });
    }
}

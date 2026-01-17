<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAmountToDevicePayingTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('device_paying_transactions', function (Blueprint $table) {
            $table->decimal('amount', 11, 2)->after('transaction_id')->nullable();

            $table->unique(['user_channel_account_id', 'amount']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('device_paying_transactions', function (Blueprint $table) {
            $table->dropUnique(['user_channel_account_id', 'amount']);

            $table->dropColumn('amount');
        });
    }
}

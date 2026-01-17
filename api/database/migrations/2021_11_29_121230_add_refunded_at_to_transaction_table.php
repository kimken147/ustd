<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRefundedAtToTransactionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->bigInteger('refunded_by_id')->unsigned()->nullable()->after('locked_by_id');
            $table->timestamp('refunded_at')->nullable()->after('to_wallet_should_settled_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('cancel_order_enable')->default(false)->after('balance_transfer_enable');
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
            $table->dropColumn('refunded_by_id');
            $table->dropColumn('refunded_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('cancel_order_enable');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSystemOrderNumberToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('system_order_number', 50)->after('order_number')->default('');
        });

        $prefix = config('transaction.system_order_number_prefix');
        DB::statement("UPDATE transactions set system_order_number = CONCAT('{$prefix}', DATE_FORMAT(created_at, '%Y%m%d%H%i%s'), id)");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('system_order_number');
        });
    }
}

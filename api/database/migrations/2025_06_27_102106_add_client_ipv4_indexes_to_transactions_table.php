<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddClientIpv4IndexesToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // 添加复合索引优化 client_ipv4 相关查询
            $table->index(['client_ipv4', 'status', 'created_at'], 'transactions_client_ipv4_status_created_at_index');

            // 如果需要单独的 client_ipv4 索引（用于简单的 client_ipv4 查询）
            $table->index('client_ipv4', 'transactions_client_ipv4_index');
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
            $table->dropIndex('transactions_client_ipv4_status_created_at_index');
            $table->dropIndex('transactions_client_ipv4_index');
        });
    }
}

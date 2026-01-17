<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRateLimitIndexToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // 为速率限制查询优化的索引
            // 顺序：client_ipv4 (高选择性) -> created_at (范围查询) -> status (过滤)
            $table->index(
                ['client_ipv4', 'created_at', 'status'],
                'idx_rate_limit_optimized'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_rate_limit_optimized');
        });
    }
};

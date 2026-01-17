<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddOrderNumberTypeIndexesToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // 檢查並建立 order_number + type 索引
            if (!$this->indexExists('transactions', 'idx_order_number_type')) {
                $table->index(['order_number', 'type'], 'idx_order_number_type');
            }

            // 檢查並建立 system_order_number + type 索引
            if (!$this->indexExists('transactions', 'idx_system_order_number_type')) {
                $table->index(['system_order_number', 'type'], 'idx_system_order_number_type');
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
            // 檢查並移除索引
            if ($this->indexExists('transactions', 'idx_order_number_type')) {
                $table->dropIndex('idx_order_number_type');
            }

            if ($this->indexExists('transactions', 'idx_system_order_number_type')) {
                $table->dropIndex('idx_system_order_number_type');
            }
        });
    }

    /**
     * 檢查索引是否存在
     *
     * @param string $tableName
     * @param string $indexName
     * @return bool
     */
    private function indexExists($tableName, $indexName)
    {
        $indexes = DB::select("SHOW INDEX FROM `{$tableName}` WHERE Key_name = '{$indexName}'");
        return !empty($indexes);
    }
}

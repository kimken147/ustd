<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexForTransactionForFromTypeStatusConfirmedAt extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['from_id', 'type', 'status', 'confirmed_at']);
            $table->index(['to_id', 'type', 'status', 'confirmed_at']);
            $table->index(['system_order_number', 'created_at', 'deleted_at']);
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
            $table->dropIndex(['from_id', 'type', 'status', 'confirmed_at']);
            $table->dropIndex(['to_id', 'type', 'status', 'confirmed_at']);
            $table->dropIndex(['system_order_number', 'created_at', 'deleted_at']);
        });
    }
}

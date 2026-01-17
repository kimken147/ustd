<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexForTransactionFees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_fees', function (Blueprint $table) {
            
            $table->index('transaction_id');
            $table->index(['transaction_id', 'user_id']);
            
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transaction_fees', function (Blueprint $table) {
            
            $table->dropIndex('transaction_id');
            $table->dropIndex(['transaction_id', 'user_id']);
            
        });
    }
}

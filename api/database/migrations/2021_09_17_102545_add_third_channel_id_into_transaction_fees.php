<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddThirdChannelIdIntoTransactionFees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transaction_fees', function (Blueprint $table) {
            $table->unsignedInteger('thirdchannel_id')->nullable()->after('account_mode');
            $table->dropUnique(['user_id', 'transaction_id']);
            $table->unique(['user_id', 'transaction_id', 'thirdchannel_id']);
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
            $table->dropColumn('thirdchannel_id');
            $table->dropUnique(['user_id', 'transaction_id', 'thirdchannel_id']);
            $table->unique(['user_id', 'transaction_id']);
        });
    }
}

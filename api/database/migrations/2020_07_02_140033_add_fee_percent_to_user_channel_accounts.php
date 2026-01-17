<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFeePercentToUserChannelAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->unsignedDecimal('min_amount', 8, 2)->nullable()->after('regular_customer_first');
            $table->unsignedDecimal('max_amount', 8, 2)->nullable()->after('min_amount');
            $table->unsignedDecimal('fee_percent', 4, 2)->after('max_amount');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropColumn('min_amount');
            $table->dropColumn('max_amount');
            $table->dropColumn('fee_percent');
        });
    }
}

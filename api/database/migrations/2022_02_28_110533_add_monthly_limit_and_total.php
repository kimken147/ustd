<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMonthlyLimitAndTotal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->boolean('monthly_status')->after('daily_total')->default(false);
            $table->decimal('monthly_limit', 12, 2)->after('monthly_status')->nullable();
            $table->decimal('monthly_total', 12, 2)->after('monthly_limit')->default(0);
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
            $table->dropColumn('monthly_status');
            $table->dropColumn('monthly_limit');
            $table->dropColumn('monthly_total');
        });
    }
}

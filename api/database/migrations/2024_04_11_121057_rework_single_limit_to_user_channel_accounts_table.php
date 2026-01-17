<?php

use Illuminate\Database\Migrations\Migration;

use Illuminate\Database\Schema\Blueprint;

use Illuminate\Support\Facades\Schema;


class ReworkSingleLimitToUserChannelAccountsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('user_channel_accounts', 'single_min_limit')) {
                $table->unsignedDecimal('single_min_limit', 15, 2)->after('withdraw_monthly_total')->default(null)->nullable()->comment('單筆限額收款最小限制');
            }

            if (!Schema::hasColumn('user_channel_accounts', 'single_max_limit')) {
                $table->unsignedDecimal('single_max_limit', 15, 2)->after('single_min_limit')->default(null)->nullable()->comment('單筆限額收款最大限制');
            }

            if (!Schema::hasColumn('user_channel_accounts', 'withdraw_single_min_limit')) {
                $table->unsignedDecimal('withdraw_single_min_limit', 15, 2)->after('single_max_limit')->default(null)->nullable()->comment('單筆限額出款最小限制');
            }

            if (!Schema::hasColumn('user_channel_accounts', 'withdraw_single_max_limit')) {
                $table->unsignedDecimal('withdraw_single_max_limit', 15, 2)->after('withdraw_single_min_limit')->default(null)->nullable()->comment('單筆限額出款最大限制');
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
        Schema::table('user_channel_accounts', function (Blueprint $table) {

            $table->dropColumn('single_min_limit');

            $table->dropColumn('single_max_limit');

            $table->dropColumn('withdraw_single_min_limit');

            $table->dropColumn('withdraw_single_max_limit');
        });
    }
}

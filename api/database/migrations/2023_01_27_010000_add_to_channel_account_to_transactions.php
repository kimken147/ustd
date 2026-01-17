<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddToChannelAccountToTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('_search2', 50)->default('')->after('_search1');

            $table->index('_search2');
        });

        Schema::table('transactions', function (Blueprint $table) {
            DB::statement('UPDATE transactions
                    SET _search2 = JSON_UNQUOTE(JSON_EXTRACT(to_channel_account, "$.mobile_number"))
                    WHERE from_channel_account_id IS NOT NULL
                    AND created_at >= "2023-01-01 00:00:00"');
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
            $table->dropColumn('_search2');
        });
    }
}


<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddSearch2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 代收會員帳號
        DB::statement('UPDATE transactions AS t 
                SET t._search2 = JSON_UNQUOTE(JSON_EXTRACT(to_channel_account, "$.mobile_number")) 
                WHERE t.type = 1
                AND t.created_at >= "2023-01-01 00:00:00"');

        // 代付會員帳號
        DB::statement('UPDATE transactions AS t 
                SET t._search2 = JSON_UNQUOTE(JSON_EXTRACT(from_channel_account, "$.bank_card_number")) 
                WHERE t.type = 2
                AND t.created_at >= "2023-01-01 00:00:00"');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}


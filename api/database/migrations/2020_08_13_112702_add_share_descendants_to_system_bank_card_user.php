<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddShareDescendantsToSystemBankCardUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('system_bank_card_user', function (Blueprint $table) {
            // 預設 true 為了相容之前的邏輯
            $table->boolean('share_descendants')->default(true)->after('system_bank_card_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('system_bank_card_user', function (Blueprint $table) {
            $table->dropColumn('share_descendants');
        });
    }
}

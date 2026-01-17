<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUserChannelAccountType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->tinyInteger('type')->after('status')->default(1);
            $table->bigInteger('channel_amount_id')->nullable(true)->change();
            $table->decimal('fee_percent', 4, 2)->default(0)->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->bigInteger('to_channel_account_id')->after('from_channel_account_id')->nullable();
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
            $table->dropColumn('type');
            $table->bigInteger('channel_amount_id')->nullable(false)->change();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('to_channel_account_id');
        });
    }
}

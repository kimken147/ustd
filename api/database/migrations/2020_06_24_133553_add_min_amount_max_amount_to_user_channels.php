<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMinAmountMaxAmountToUserChannels extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channels', function (Blueprint $table) {
            $table->unsignedDecimal('min_amount', 8, 2)->after('status')->nullable();
            $table->unsignedDecimal('max_amount', 8, 2)->after('min_amount')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_channels', function (Blueprint $table) {
            $table->dropColumn('max_amount');
            $table->dropColumn('min_amount');
        });
    }
}

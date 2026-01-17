<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddFixedAmountToChannelAmounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channel_amounts', function (Blueprint $table) {
            $table->decimal('min_amount', 8, 2)->nullable()->change();
            $table->decimal('max_amount', 8, 2)->nullable()->change();

            $table->json('fixed_amount')->after('max_amount')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channel_amounts', function (Blueprint $table) {
            $table->dropColumn('fixed_amount');
        });
    }
}

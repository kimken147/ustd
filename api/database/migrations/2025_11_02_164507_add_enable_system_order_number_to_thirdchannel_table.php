<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEnableSystemOrderNumberToThirdchannelTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->boolean('enable_system_order_number')
                ->default(false)
                ->after('white_ip')
                ->comment('啟用系統訂單號');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->dropColumn('enable_system_order_number');
        });
    }
}

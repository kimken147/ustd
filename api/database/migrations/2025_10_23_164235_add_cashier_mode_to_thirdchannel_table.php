<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCashierModeToThirdchannelTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->unsignedTinyInteger('cashier_mode')
                ->default(1)
                ->after('custom_url')
                ->comment('收银台模式');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            $table->dropColumn('cashier_mode');
        });
    }
}

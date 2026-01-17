<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKey4ToThirdchannelTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            if (!Schema::hasColumn('thirdchannel', 'key4')) {
                $table->text('key4')->after('key3')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thirdchannel', function (Blueprint $table) {
            if (Schema::hasColumn('thirdchannel', 'key4')) {
                $table->dropColumn('key4');
            }
        });
    }
};

<?php

use App\Models\Channel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddLayoutVersionToChannel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('cashier_version', 50)->default('')->after('country');
        });

        DB::table('channels')->where('code', Channel::CODE_GCASH)->update(['cashier_version' => 'v1']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('cashier_version');
        });
    }
}


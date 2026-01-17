<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Stevebauman\Location\Facades\Location;

use App\Models\User;

class AddGeolocationEnableForChannel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->boolean('geolocation_match')->after('country')->default(false);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->char('last_login_city', 255)->after('last_login_ipv4')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('geolocation_match');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_login_city');
        });
    }
}

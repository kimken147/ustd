<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProvinceAndCity extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('system_bank_cards', function (Blueprint $table) {
            $table->string('bank_province')->after('bank_name')->nullable();
            $table->string('bank_city')->after('bank_province')->nullable();
        });

        Schema::table('bank_cards', function (Blueprint $table) {
            $table->string('bank_province')->after('bank_name')->nullable();
            $table->string('bank_city')->after('bank_province')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('system_bank_cards', function (Blueprint $table) {
            $table->dropColumn('bank_province');
            $table->dropColumn('bank_city');
        });

        Schema::table('bank_cards', function (Blueprint $table) {
            $table->dropColumn('bank_province');
            $table->dropColumn('bank_city');
        });
    }
}

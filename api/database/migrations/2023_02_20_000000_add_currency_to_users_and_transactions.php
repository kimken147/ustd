<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddCurrencyToUsersAndTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('currency', 5)->default('')->after('note');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('currency', 5)->default('')->after('tags');
        });
        Schema::table('banks', function (Blueprint $table) {
            $table->string('code', 50)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
}


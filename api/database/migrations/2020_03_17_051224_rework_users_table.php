<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kalnoy\Nestedset\NestedSet;

class ReworkUsersTable extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email');
            $table->dropColumn('email_verified_at');
            $table->dropColumn('remember_token');

            // id
            $table->unsignedBigInteger(Nestedset::LFT)->default(0)->after('id');
            $table->unsignedBigInteger(NestedSet::RGT)->default(0)->after(NestedSet::LFT);
            $table->unsignedBigInteger(NestedSet::PARENT_ID)->nullable()->after(NestedSet::RGT);
            $table->unsignedInteger('last_login_ipv4')->nullable()->after(NestedSet::PARENT_ID);
            $table->unsignedTinyInteger('role')->after('last_login_ipv4');
            $table->unsignedTinyInteger('status')->default(true)->after('role');
            $table->boolean('agent_enable')->default(false)->after('status');
            $table->boolean('google2fa_enable')->default(false)->after('agent_enable');
            // password
            $table->string('secret_key', 32)->unique()->after('password');
            $table->string('name', 20)->after('secret_key');
            $table->string('username', 20)->unique()->after('name');
            $table->char('google2fa_secret', 16)->unique()->after('username');
            // created_at
            // updated_at
            $table->timestamp('last_login_at')->nullable()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(Nestedset::LFT);
            $table->dropColumn(NestedSet::RGT);
            $table->dropColumn(NestedSet::PARENT_ID);
            $table->dropColumn('last_login_ipv4');
            $table->dropColumn('role');
            $table->dropColumn('status');
            $table->dropColumn('agent_enable');
            $table->dropColumn('google2fa_enable');
            $table->dropColumn('secret_key');
            $table->dropColumn('username');
            $table->dropColumn('google2fa_secret');
            $table->dropColumn('last_login_at');

            // id
            $table->string('name')->after('id');
            $table->string('email')->after('name');
            $table->timestamp('email_verified_at')->nullable()->after('email');
            // password
            $table->rememberToken()->after('password');
        });
    }
}

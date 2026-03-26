<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chain_transactions', function (Blueprint $table) {
            $table->string('token_type', 10)->default('USDT')->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('chain_transactions', function (Blueprint $table) {
            $table->dropColumn('token_type');
        });
    }
};

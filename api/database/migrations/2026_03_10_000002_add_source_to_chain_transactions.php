<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chain_transactions', function (Blueprint $table) {
            $table->string('source', 20)->default('sync')->after('confirmations')
                ->comment('sync=鏈上同步, internal=內轉主動建立');
        });
    }

    public function down(): void
    {
        Schema::table('chain_transactions', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};

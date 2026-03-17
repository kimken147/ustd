<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->boolean('is_one_time')->default(false)->after('derivation_index')
                ->comment('是否為一次性收款地址');
            $table->string('receive_status', 20)->default('none')->after('is_one_time')
                ->comment('收款狀態: none=非一次性, unused=待收款, used=已收款');
            $table->unsignedBigInteger('linked_transaction_id')->nullable()->after('receive_status')
                ->comment('綁定的收款交易 ID');

            $table->index(['is_one_time', 'receive_status']);
            $table->index('linked_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropIndex(['linked_transaction_id']);
            $table->dropIndex(['is_one_time', 'receive_status']);
            $table->dropColumn(['is_one_time', 'receive_status', 'linked_transaction_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->string('address_type', 10)->default('master')->after('account')
                ->comment('master=主地址, child=子地址');
            $table->unsignedInteger('parent_account_id')->nullable()->after('address_type')
                ->comment('子地址的母地址帳號 ID');
            $table->unsignedInteger('derivation_index')->nullable()->after('parent_account_id')
                ->comment('HD 衍生路徑 index');

            $table->index('address_type');
            $table->index('parent_account_id');
            $table->unique(['parent_account_id', 'derivation_index'], 'uca_parent_derivation_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropUnique('uca_parent_derivation_unique');
            $table->dropIndex(['parent_account_id']);
            $table->dropIndex(['address_type']);
            $table->dropColumn(['address_type', 'parent_account_id', 'derivation_index']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddAutosyncToUserChannelAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->boolean('auto_sync')->default(false)->after('is_auto');

            if (collect(DB::select("SHOW INDEXES FROM user_channel_accounts"))->pluck('Key_name')->contains('user_channel_accounts_channel_code_status_type_index')) {
                $table->dropIndex(['channel_code', 'status', 'type']);
            }
            if (!collect(DB::select("SHOW INDEXES FROM user_channel_accounts"))->pluck('Key_name')->contains('query_index1')) {
                $table->index(['channel_code', 'type', 'status', 'auto_sync', 'account', 'name'], 'query_index1');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropColumn('auto_sync');
        });
    }
}


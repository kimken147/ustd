<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddNotesToUserChannelAccountAudit extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_account_audits', function (Blueprint $table) {
            $table->string('note', 255)->default('')->after('new_value');
        });
        
        Schema::table('wallet_histories', function (Blueprint $table) {
            if (!collect(DB::select("SHOW INDEXES FROM wallet_histories"))->pluck('Key_name')->contains('user_id_type_note_created_at')) {
                $table->index(['user_id', 'type', 'note', 'created_at'], 'user_id_type_note_created_at');
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
        Schema::table('user_channel_account_audits', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
}


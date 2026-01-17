<?php

use App\Model\Channel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnNoteType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->tinyInteger('note_type')->after('real_name_enable')->nullable();
        });
        DB::table('channels')
            ->whereIn('code', [Channel::CODE_BANK_CARD, Channel::CODE_ALIPAY_BANK])
            ->update([
                'note_type'   => Channel::NOTE_TREASURE,
            ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('note_type');
        });
    }
}

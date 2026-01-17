<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Models\UserChannelAccount;

class UpdateUserChannelAccountType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 更改 type 數值，原本的值也要修改 1(代收) 改成 代收付  2(代付) 改成 代收 代付則是 3
        UserChannelAccount::withTrashed()->where('type', 2)->update(['type' => 3]);
        UserChannelAccount::withTrashed()->where('type', 1)->update(['type' => 2]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        UserChannelAccount::withTrashed()->where('type', 2)->update(['type' => 1]);
        UserChannelAccount::withTrashed()->where('type', 3)->update(['type' => 2]);
    }
}

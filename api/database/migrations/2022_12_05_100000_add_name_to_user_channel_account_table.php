<?php

use App\Model\UserChannelAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Hashids\Hashids;

class AddNameToUserChannelAccountTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->string('name', 255)->after('id')->default('');
        });

        foreach (UserChannelAccount::withTrashed()->get() as $account) {
            $account->name = (new Hashids())->encode($account->getKey());
            $account->save();
        }

        Schema::table('banks', function (Blueprint $table) {
            $table->text('tags', 255)->after('code')->nullable();
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
            $table->dropColumn('name');
        });

        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
}

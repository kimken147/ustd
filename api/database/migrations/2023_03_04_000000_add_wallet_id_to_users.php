<?php

use App\Model\User;
use App\Model\Wallet;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddWalletIdToUsers extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('wallet_id')->after('parent_id')->default(0);
        });

        foreach (User::get() as $user) {
            $wallet = Wallet::firstWhere('user_id', $user->id);
            if ($wallet) {
                $user->update(['wallet_id' => $wallet->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wallet_id');
        });
    }
}


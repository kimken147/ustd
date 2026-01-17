<?php

use Hashids\Hashids;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

class SetUserWalletProfitTo0 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $users = User::where('role', User::ROLE_MERCHANT)->get();

        foreach ($users as $user) {
            $user->wallet->update(['profit' => 0]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

    }
}

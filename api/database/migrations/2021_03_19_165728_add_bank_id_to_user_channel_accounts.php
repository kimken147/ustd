<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Model\Bank;
use App\Model\UserChannelAccount;

class AddBankIdToUserChannelAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('user_channel_accounts', 'bank_id')) {
            Schema::table('user_channel_accounts', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_id')->default('0')->after('wallet_id');
            });

            $accounts = UserChannelAccount::withTrashed()->get();

            $accounts->each(function ($account) {
                $bank = Bank::firstWhere('name', $account->detail['bank_name']);
                $account->update([
                    'bank_id' => $bank->id
                ]);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropColumn('bank_id');
        });
    }
}

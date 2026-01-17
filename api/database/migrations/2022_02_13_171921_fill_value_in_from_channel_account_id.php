<?php

use Hashids\Hashids;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

class FillValueInFromChannelAccountId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $count = DB::table('transactions')->whereNotNull('from_channel_account_hash_id')->count();
        $page = $count/10000;

        for($i = 0; $i < $page; $i++) {
            $transactions = DB::table('transactions')
            ->select('id', 'from_channel_account_hash_id')
            ->whereNotNull('from_channel_account_hash_id')
            ->paginate(1000);

            foreach ($transactions as $transaction) {
                DB::table('transactions')
                ->where('id', $transaction->id)
                ->update([
                    'from_channel_account_id' => Arr::first((new Hashids())->decode($transaction->from_channel_account_hash_id))
                ]);
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

    }
}

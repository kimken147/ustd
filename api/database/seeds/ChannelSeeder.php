<?php

use App\Models\Channel;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('channels')->insertOrIgnore([
            'code' => Channel::CODE_USDT,
            'name' => 'USDT',
            'status' => true,
            'type' => Channel::TYPE_DEPOSIT_WITHDRAW,
            'order_timeout' => 30,
            'order_timeout_enable' => true,
            'transaction_timeout' => 30,
            'transaction_timeout_enable' => true,
            'floating' => 0,
            'floating_enable' => false,
            'present_result' => Channel::RESPONSE_FORM,
        ]);
    }
}

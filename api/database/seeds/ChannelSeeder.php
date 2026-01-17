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
            'code' => Channel::CODE_QR_ALIPAY,
            'name' => '支付宝扫码',
            'status' => true,
            'order_timeout' => 30,
            'order_timeout_enable' => true,
            'transaction_timeout' => 30,
            'transaction_timeout_enable' => true,
            'floating' => 1,
            'floating_enable' => false,
            'present_result' => Channel::RESPONSE_QRCODE
        ]);

        DB::table('channels')->insertOrIgnore([
            'code' => Channel::CODE_ALIPAY_BANK,
            'name' => '支转银',
            'status' => true,
            'order_timeout' => 30,
            'order_timeout_enable' => true,
            'transaction_timeout' => 30,
            'transaction_timeout_enable' => true,
            'floating' => 1,
            'floating_enable' => false,
            'present_result' => Channel::RESPONSE_BANK_CARD,
        ]);
    }
}

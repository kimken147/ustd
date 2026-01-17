<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Model\UserChannelAccount;
use App\Model\Channel;
use Illuminate\Support\Str;
class AddTypeForChannel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->unsignedTinyInteger('type')->after('status')->default(2);
            $table->json('deposit_account_fields')->after('geolocation_match');
            $table->json('withdraw_account_fields')->after('deposit_account_fields');
        });
        foreach (Channel::get() as $channel) {
            $update = [];
            if (in_array($channel->code, ['BANK_CARD', 'USDT', 'GCASH'])) {
                $update['type'] = Channel::TYPE_DEPOSIT_WITHDRAW;
            }

            if (in_array($channel->code, ['BANK_CARD', 'ALIPAY_BANK', 'QR_BANK_CARD'])) {
                $update['deposit_account_fields'] = [
                    'bank_name' => '银行名称',
                    'bank_card_number' => '银行卡号',
                    'bank_card_holder_name' => '持卡人',
                    'bank_branch' => '分行'
                ];
                $update['withdraw_account_fields'] = [
                    'bank_name' => '银行名称',
                    'bank_card_number' => '银行卡号',
                    'bank_card_holder_name' => '持卡人',
                    'bank_branch' => '分行'
                ];
            } else if ($channel->code == 'GCASH') {
                $update['deposit_account_fields'] = [
                    'account' => '账号'
                ];
                $update['withdraw_account_fields'] = [
                    'account' => '账号',
                    'mpin' => 'MPin',
                    'receiver_name' => '姓名'
                ];
            } else if ($channel->code == 'USDT') {
                $update['deposit_account_fields'] = [
                    'account' => '钱包地址'
                ];
                $update['withdraw_account_fields'] = [
                    'account' => '钱包地址',
                    'receiver_name' => '姓名'
                ];
            } else if ($channel->code == 'MOMOPAY' || Str::startsWith($channel->code, 'DC_')) {
                $update['deposit_account_fields'] = [
                    'account' => '账号',
                    'mobile' => '手机'
                ];
            } else if (in_array($channel->code, ['RE_QQ', 'RE_ALIPAY'])) {
                $update['deposit_account_fields'] = [
                    'account' => '账号',
                    'receiver_name' => '姓名'
                ];
            } else if (Str::startsWith($channel->code, 'QR_')) {
                $update['deposit_account_fields'] = [
                    'account' => '账号',
                    'receiver_name' => '收款人姓名/手机',
                    'qr_code' => '二维码'
                ];
            } else if ($channel->code == 'RE_DINGDING') {
                $update['deposit_account_fields'] = [
                    'account' => '群名称',
                    'receiver_name' => '群主名称',
                    'qr_code' => '二维码'
                ];
            }

            $channel->update($update);
        }

        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->char('channel_code', 20)->after('user_id')->nullable();
        });

        foreach (UserChannelAccount::withTrashed()->get() as $account) {
            if ($account->channelAmount) {
                $channelCode = $account->channelAmount->channel_code;
                $detail = $account->detail;

                if ($channelCode == Channel::CODE_GCASH) {
                    $accountNo = Str::startsWith($account->account, '0') ? $account->account : '0'.$account->account;
                } else {
                    $accountNo = $account->account;
                }

                $detail['account'] = $accountNo;
                $account->update([
                    'channel_code' => $channelCode,
                    'detail' => $detail,
                    'account' => $accountNo
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
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->dropColumn('deposit_account_fields');
            $table->dropColumn('withdraw_account_fields');
        });

        Schema::table('user_channel_accounts', function (Blueprint $table) {
            $table->dropColumn('channel_code');
        });
    }
}

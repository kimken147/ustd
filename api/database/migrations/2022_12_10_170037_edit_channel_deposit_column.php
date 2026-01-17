<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Model\Channel;

class EditChannelDepositColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach (Channel::get() as $channel) {
            $update = [];
            if (in_array($channel->code, ['BANK_CARD'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'bank_name' => '银行名称',
                        'bank_card_branch' => '分行',
                        'bank_card_number' => '银行卡号',
                        'bank_card_holder_name' => '持卡人'
                    ]
                ];
            }
            if (in_array($channel->code, ['DC_BANK'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'otp' => 'OTP',
                        'pin' => 'PIN(请输入银行pin码 有则必填)',
                        'pwd' => 'PWD',
                        'bank_name' => '银行名称',
                        'account' => '账号',
                        'bank_card_holder_name' => '持卡人'
                    ],
                    'auto_daifu_banks' => ["ACB", "BIDV", "EIB", "ICB", "MSB", "TCB", "VIB", "VCB"],
                    'auto_daiso_banks' => ["ACB", "BIDV", "EIB", "ICB", "MB", "TPB", "VCB"],
                    'user_can_deposit_banks' => [],
                    'merchant_can_withdraw_banks' => ["ABB", "ACB", "AGR", "BAB", "BVB", "BIDV", "CBB", "CIMB", "DAB", "EIB", "GPB", "HDB", "HLB", "ICB", "IVB", "KLB", "LVB", "MSB", "MB", "NAB", "NCB", "OCB", "OJB", "PBVN", "PGB", "PVB", "STB", "SGB", "SCB", "SEAB", "SHB", "SHBVN", "TCB", "TPB", "UOB", "VAB", "VCAPB", "VRB", "VIB", "VIETB", "VCB", "VPB", "WOO", "YOLO"]
                ];
            }
            if (in_array($channel->code, ['QR_BANK'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'otp' => 'OTP',
                        'pin' => 'PIN(请输入银行pin码 有则必填)',
                        'pwd' => 'PWD',
                        'bank_name' => '银行名称',
                        'bank_card_branch' => '分行',
                        'bank_card_number' => '银行卡号',
                        'bank_card_holder_name' => '持卡人'
                    ],
                    'auto_daifu_banks' => ["ACB", "BIDV", "EIB", "ICB", "MSB", "TCB", "VIB", "VCB"],
                    'auto_daiso_banks' => ["ACB", "AGR", "BIDV", "EIB", "HDB", "ICB", "MB", "MSB", "NAB", "SCB", "SHB", "TCB", "TPB", "VCB", "VIB", "VPB"],
                    'user_can_deposit_banks' => [],
                    'merchant_can_withdraw_banks' => ["ABB", "ACB", "AGR", "BAB", "BVB", "BIDV", "CBB", "CIMB", "DAB", "EIB", "GPB", "HDB", "HLB", "ICB", "IVB", "KLB", "LVB", "MSB", "MB", "NAB", "NCB", "OCB", "OJB", "PBVN", "PGB", "PVB", "STB", "SGB", "SCB", "SEAB", "SHB", "SHBVN", "TCB", "TPB", "UOB", "VAB", "VCAPB", "VRB", "VIB", "VIETB", "VCB", "VPB", "WOO", "YOLO"]
                ];
            }
            if (in_array($channel->code, ['QR_MOMOPAY'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'account' => '账号',
                        'qr_code' => '二维码'
                    ]
                ];
            }
            if (in_array($channel->code, ['GCASH'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'mpin' => 'MPin',
                        'account' => '账号'
                    ],
                    'merchant_can_withdraw_banks' => ["GCash"]
                ];
            }
            if (in_array($channel->code, ['QR_GCASH'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'mpin' => 'MPin',
                        'account' => '账号',
                        'qr_code' => '二维码'
                    ]
                ];
            }
            if (in_array($channel->code, ['QR_ALIPAY', 'QR_QQ', 'QR_WECHATPAY', 'QR_YFB'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'account' => '账号',
                        'qr_code' => '二维码',
                        'receiver_name' => '收款人姓名或手机'
                    ]
                ];
            }
            if (in_array($channel->code, ['RE_ALIPAY', 'RE_QQ'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'account' => '账号',
                        'receiver_name' => '收款人姓名'
                    ]
                ];
            }
            if (in_array($channel->code, ['ALIPAY_BANK'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'bank_name' => '银行名称',
                        'bank_card_branch' => '分行',
                        'bank_card_number' => '银行卡号',
                        'bank_card_holder_name' => '持卡人'
                    ]
                ];
            }
            if (in_array($channel->code, ['USDT'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'account' => '钱包地址'
                    ]
                ];
            }
            if (in_array($channel->code, ['RE_DINGDING'])) {
                $update['deposit_account_fields'] = [
                    'fields' => [
                        'account' => '群名称',
                        'qr_code' => '二维码',
                        'receiver_name' => '群主名称'
                    ]
                ];
            }
            $channel->update($update);
        }

        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('withdraw_account_fields');
        });
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

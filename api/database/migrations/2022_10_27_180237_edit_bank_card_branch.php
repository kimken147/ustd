<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Channel;

class EditBankCardBranch extends Migration
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
            if (in_array($channel->code, ['BANK_CARD', 'ALIPAY_BANK'])) {
                $update['deposit_account_fields'] = [
                    'bank_name' => '银行名称',
                    'bank_card_number' => '银行卡号',
                    'bank_card_holder_name' => '持卡人',
                    'bank_card_branch' => '分行'
                ];
            }
            $channel->update($update);
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

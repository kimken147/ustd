<?php


namespace App\Utils;

use App\Model\User;
use App\Model\UserChannel;
use Illuminate\Http\Response;

class ChannelCheckUtil
{
    private $msg = '';
    public function checkChannelFail($user, $parent_id){
        $self_channel = UserChannel::where(['user_id' => $user,'status' => 1])->get();

        foreach ($self_channel as $k => $v) {
            $parent_channel = UserChannel::where([
                'user_id' => $parent_id,
                'status' => 1,
                'channel_group_id'=>$v->channel_group_id,
            ])->first();

            //从上级未找到相同通道有开启
            if(!isset($parent_channel)){
                $this->msg = '此商户启用的[通道]上级代理非启用';
                return true;
            }

           //检查费率
            if($parent_channel->fee_percent > $v->fee_percent){
                $this->msg = '上级费率需 <= 自身费率(' . $parent_channel->fee_percent . '>' . $v->fee_percent.')';
                return true;
            }
        }

        return false;
    }

    public function abortForbiddenIfcheckChannelFailed($user, $parent_id)
    {
        abort_if(
            $this->checkChannelFail($user, $parent_id),
            Response::HTTP_FORBIDDEN,
            $this->msg
        );
    }
}

<?php

namespace App\Http\Resources;

use \Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Model\User;
use App\Model\Message as MessageModel;

class MessageContact extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $userId = $this->id;

        $lastMessage = MessageModel::with('from', 'to')->where(function ($builder) use ($userId) {
            if (\Auth::user()->isProvider()) {
                $builder->where('to_id', \Auth::id());
            } else {
                $builder->where('from_id', $userId)->orWhere('to_id', $userId);
            }
        })->orderByDesc('created_at')->first();

        $nickname = in_array($this->role, [User::ROLE_ADMIN, User::ROLE_SUB_ACCOUNT]) ? '客服' : $this->name;
        return [
            'id' => $this->id,
            'account' => $this->username,
            'nickname' => $nickname,
            'unread_count' => \Auth::user()->unreadMessages($userId)->count(),
            'last_message' => $lastMessage ? Message::make($lastMessage) : null
        ];
    }
}

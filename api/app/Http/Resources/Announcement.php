<?php

namespace App\Http\Resources;

use \Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends JsonResource
{
    use SoftDeletes;

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $user = auth()->user();
        if (!($user->isAdmin() || $user->isSubAccount())) {
            return [
                'id' => $this->id,
                'title' => $this->title,
                'content' => $this->content,
                'started_at' => optional($this->started_at)->timezone(config('app.timezone')),
                'ended_at' => optional($this->ended_at)->timezone(config('app.timezone'))
            ];
        }
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'notes' => $this->notes,
            'targets' => $this->whenLoaded('users', function () {
                return User::whereIn('id', $this->users->pluck('user_id'))->select('id','name','username','role')->get();
            }),
            'for_merchant' => $this->for_merchant,
            'for_provider' => $this->for_provider,
            'started_at' => optional($this->started_at)->timezone(config('app.timezone')),
            'ended_at' => optional($this->ended_at)->timezone(config('app.timezone'))
        ];
    }
}

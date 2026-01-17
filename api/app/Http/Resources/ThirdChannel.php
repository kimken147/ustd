<?php

namespace App\Http\Resources;

use App\Model\Device;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * @property Device|null device
 */
class ThirdChannel extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->getKey(),
            'name' => $this->name,
            'thirdChannel' => $this->name . '(' . $this->merchant_id . ')',
            'class' => $this->class,
            'status' => $this->status,
            'custom_url' => $this->custom_url,
            'white_ip' => $this->white_ip,
            'channel' => $this->channel->name,
            'type' => $this->type,
            'auto_daifu_threshold' => $this->auto_daifu_threshold,
            'auto_daifu_threshold_min' => $this->auto_daifu_threshold_min,
            'merchant_id' => $this->merchant_id,
            'balance' => $this->balance,
            'notify_balance' => $this->notify_balance,
            'key' => $this->key,
            'key2' => $this->key2,
            'key3' => $this->key3,
            'merchants' => $this->merchants_count,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}

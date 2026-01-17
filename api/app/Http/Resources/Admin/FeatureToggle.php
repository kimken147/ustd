<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null updated_at
 * @property boolean enabled
 */
class FeatureToggle extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'         => $this->getKey(),
            'enabled'    => $this->enabled,
            'label'      => __('feature-toggle.' . $this->getKey()),
            'note'       => __('feature-toggle-note.' . $this->getKey()),
            'type'       => $this->getInput('type'),
            'unit'       => $this->getInput('unit') ? __('unit.' . $this->getInput('unit')) : '',
            'value'      => $this->getInput('value'),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

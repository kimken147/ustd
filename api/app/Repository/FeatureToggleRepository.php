<?php

namespace App\Repository;

use App\Models\FeatureToggle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FeatureToggleRepository
{
    private $caches;

    public function __construct()
    {
        // 移除直接查詢，改為初始化空集合
        $this->caches = new Collection();
    }

    // 加入一個延遲加載的方法
    private function loadFeatures()
    {
        if ($this->caches->isEmpty()) {
            try {
                $this->caches = FeatureToggle::get()->keyBy('id');
            } catch (\Exception $e) {
                Log::error('Failed to load feature toggles: ' . $e->getMessage());
                $this->caches = new Collection();
            }
        }
    }

    public function enabled($feature, $noCache = false)
    {
        $featureToggle = $this->find($feature, $noCache);
        return (bool) optional($featureToggle)->enabled;
    }

    private function find($feature, $noCache = false): ?FeatureToggle
    {
        // 只在實際需要時才加載特性開關
        if (!$noCache) {
            $this->loadFeatures();
            $cache = $this->caches->get($feature);
            if ($cache instanceof FeatureToggle) {
                return $cache;
            }
        }

        $featureToggle = FeatureToggle::find($feature);
        if ($featureToggle) {
            $this->caches[$feature] = $featureToggle;
        }

        return $featureToggle;
    }

    public function valueOf($feature, $default = null, $noCache = false)
    {
        $featureToggle = $this->find($feature, $noCache);
        return data_get(optional($featureToggle), 'input.value', $default);
    }
}

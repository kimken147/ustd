<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\FeatureToggleCollection;
use App\Models\FeatureToggle;

class FeatureToggleController extends Controller
{

    public function index()
    {
        return FeatureToggleCollection::make(FeatureToggle::where('hidden', false)->whereIn('id', [])->get());
    }
}

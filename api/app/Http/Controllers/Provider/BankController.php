<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankCollection;
use App\Models\Bank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BankController extends Controller
{

    public function index(Request $request)
    {
    	$this->validate($request, ['no_paginate' => 'nullable|boolean']);

        $banks = Bank::query();

        $banks->when($request->filled('name'), function (Builder $banks) use ($request) {
            $name = implode('%', mb_str_split($request->input('name')));

            $banks->where('name', 'like', "%$name%");
        });

        return BankCollection::make($request->no_paginate ? $banks->get() : $banks->paginate(20));
    }
}

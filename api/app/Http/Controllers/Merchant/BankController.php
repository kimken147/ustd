<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankCollection;
use App\Models\Bank;
use Illuminate\Http\Request;

class BankController extends Controller
{

    public function index(Request $request)
    {
        $this->validate($request, ['no_paginate' => 'nullable|boolean']);

        $banks = Bank::query();

        if ($request->has('currency')) {
            $banks = $banks->where('currency', $request->currency);
        }

        return BankCollection::make($request->no_paginate ? $banks->get() : $banks->paginate(20));
    }
}

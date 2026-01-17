<?php

namespace App\Http\Controllers\Exchange;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankCollection;
use App\Model\Bank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BankController extends Controller
{

    public function index(Request $request)
    {
        $banks = Bank::query();

        $banks->when($request->filled('name'), function (Builder $banks) use ($request) {
            $name = implode('%', mb_str_split($request->input('name')));

            $banks->where('name', 'like', "%$name%");
        });

        return BankCollection::make($banks->paginate());
    }
}

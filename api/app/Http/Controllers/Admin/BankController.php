<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BankCollection;
use App\Models\Bank;
use App\Models\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BankController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:'.Permission::ADMIN_UPDATE_FEATURE_TOGGLE])->except(['index']);
    }

    public function destroy(Bank $bank)
    {
        $bank->delete();

        return response()->noContent();
    }

    public function exportCsv(Request $request)
    {
        $banks = Bank::query();
        $banks->when($request->filled('name'), function (Builder $banks) use ($request) {
            $name = implode('%', mb_str_split($request->input('name')));

            $banks->where('name', 'like', "%$name%");
        });

        return response()->streamDownload(
            function () use ($banks) {
                $handle = fopen('php://output', 'w');
                fputs($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // for UTF-8 BOM

                fputcsv($handle, [
                    '银行名称',
                ]);

                $banks->chunkById(
                    100,
                    function ($chunk) use ($handle) {
                        foreach ($chunk as $bank) {
                            /** @var Bank $bank */
                            fputcsv($handle, [
                                $bank->name,
                            ]);
                        }
                    }
                );

                fclose($handle);
            },
            '系统支援银行'.now()->format('Ymd').'.csv'
        );
    }

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

    public function show(Bank $bank)
    {
        return \App\Http\Resources\Bank::make($bank);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|max:20',
        ]);

        abort_if(
            Bank::where('name', $request->input('name'))->exists(),
            Response::HTTP_BAD_REQUEST,
            '银行重复'
        );

        return \App\Http\Resources\Bank::make(
            Bank::create([
                'name' => $request->input('name'),
            ])
        );
    }

    public function update(Request $request, Bank $bank)
    {
        $this->validate($request, [
            'name' => 'required|max:20',
        ]);

        abort_if(
            Bank::where('name', $request->input('name'))->where('id', '!=', $bank->getKey())->exists(),
            Response::HTTP_BAD_REQUEST,
            '银行重复'
        );

        $bank->update(['name' => $request->input('name')]);

        return \App\Http\Resources\Bank::make($bank);
    }
}

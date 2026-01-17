<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Model\TransactionNote;
use App\Model\Transaction;
use App\Http\Resources\Admin\TransactionNote as TransactionNoteResource;
use App\Http\Resources\Admin\TransactionNoteCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TransactionNoteController extends Controller
{
    public function index(Transaction $transaction)
    {
        return TransactionNoteCollection::make($transaction->transactionNotes->load('user'));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'transaction_id'    => 'required|int',
            'note'              => 'required|string|max:50'
        ]);

        /** @var TransactionNote $note */
        $note = TransactionNote::create([
            'transaction_id'    => $request->transaction_id,
            'user_id'   => auth()->user()->realUser()->getKey(),
            'note'      => $request->note,
        ]);

        return TransactionNoteResource::make($note->load('user'));
    }

    public function show(TransactionNote $transactionNote)
    {
        return TransactionNoteResource::make($transactionNote->load('user'));
    }
}

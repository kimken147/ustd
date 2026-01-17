<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Model\TransactionNote;
use App\Http\Resources\Provider\TransactionNote as TransactionNoteResource;
use App\Http\Resources\Provider\TransactionNoteCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TransactionNoteController extends Controller
{
    public function index($transactionId)
    {
        $transactionNotes = TransactionNote::where('transaction_id', $transactionId)
            ->where('user_id', auth()->user()->realUser()->getKey())
            ->get();

        return TransactionNoteCollection::make($transactionNotes);
    }
    
    public function store(Request $request)
    {
        $this->validate($request, [
            'transaction_id'    => 'required|int',
            'note'              => 'required|string|max:50'
        ]);

        $note = TransactionNote::create([
            'transaction_id'    => $request->transaction_id,
            'user_id'   => auth()->user()->realUser()->getKey(),
            'note'      => $request->note,
        ]);

        return TransactionNoteResource::make($note);
    }
    
    public function show(TransactionNote $transactionNote)
    {
        abort_if($transactionNote->user_id !== auth()->user()->realUser()->getKey(), Response::HTTP_NOT_FOUND);

        return TransactionNoteResource::make($transactionNote);
    }
}

<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Models\TransactionNote;
use App\Models\Transaction;
use App\Http\Resources\Merchant\TransactionNote as TransactionNoteResource;
use App\Http\Resources\Merchant\TransactionNoteCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TransactionNoteController extends Controller
{
    public function index(Transaction $transaction)
    {
        return TransactionNoteCollection::make($transaction->transactionNotes->load('user'));
    }

    public function show(TransactionNote $transactionNote)
    {
        return TransactionNoteResource::make($transactionNote->load('user'));
    }
}

<?php

namespace App\Services;

use App\Models\TransactionNote;

class TransactionNoteService
{
    private TransactionNote $transactionNoteModel;
    public function __construct(TransactionNote $transactionNoteModel)
    {
        $this->transactionNoteModel = $transactionNoteModel;
    }

    public function create(Int $transactionId, string $note, Int $userId = 0)
    {
        return $this->transactionNoteModel::create([
            "transaction_id" => $transactionId,
            "note" => $note,
            "user_id" => $userId
        ]);
    }
}

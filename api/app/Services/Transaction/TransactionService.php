<?php

namespace App\Services\Transaction;

use App\Models\Transaction;

class TransactionService
{
    public function findOne(string $id)
    {
        return Transaction::find($id);
    }

    public function findOneByOrderId(string $orderId)
    {
        return Transaction::where("id", $orderId)
            ->first();
    }
}

<?php

namespace App\Services\Transaction;

use App\Model\Transaction;

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

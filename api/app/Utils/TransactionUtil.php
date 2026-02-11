<?php

namespace App\Utils;

use App\Services\Transaction\TransactionLockService;
use App\Services\Transaction\TransactionStatusService;

class TransactionUtil
{
    private $transactionStatusService;

    private $transactionLockService;

    public function __construct(
        TransactionStatusService $transactionStatusService,
        TransactionLockService $transactionLockService
    ) {
        $this->transactionStatusService = $transactionStatusService;
        $this->transactionLockService = $transactionLockService;
    }

    /** @deprecated Use TransactionStatusService::markAsPaufenWithdraw() */
    public function markAsPaufenWithdraw(...$args)
    {
        return $this->transactionStatusService->markAsPaufenWithdraw(...$args);
    }

    /** @deprecated Use TransactionStatusService::markAsThirdChannelWithdraw() */
    public function markAsThirdChannelWithdraw(...$args)
    {
        return $this->transactionStatusService->markAsThirdChannelWithdraw(...$args);
    }

    /** @deprecated Use TransactionStatusService::markAsReceived() */
    public function markAsReceived(...$args)
    {
        return $this->transactionStatusService->markAsReceived(...$args);
    }

    /** @deprecated Use TransactionStatusService::markAsSuccess() */
    public function markAsSuccess(...$args)
    {
        return $this->transactionStatusService->markAsSuccess(...$args);
    }

    /** @deprecated Use TransactionStatusService::settleToWallet() */
    public function settleToWallet(...$args)
    {
        return $this->transactionStatusService->settleToWallet(...$args);
    }

    /** @deprecated Use TransactionStatusService::markPaufenTransactionAsPartialSuccess() */
    public function markPaufenTransactionAsPartialSuccess(...$args)
    {
        return $this->transactionStatusService->markPaufenTransactionAsPartialSuccess(...$args);
    }

    /** @deprecated Use TransactionStatusService::markAsFailed() */
    public function markAsFailed(...$args)
    {
        return $this->transactionStatusService->markAsFailed(...$args);
    }

    /** @deprecated Use TransactionStatusService::markAsRefunded() */
    public function markAsRefunded(...$args)
    {
        return $this->transactionStatusService->markAsRefunded(...$args);
    }

    /** @deprecated Use TransactionStatusService::rollbackAsPaying() */
    public function rollbackAsPaying(...$args)
    {
        return $this->transactionStatusService->rollbackAsPaying(...$args);
    }

    /** @deprecated Use TransactionStatusService::separateWithdraw() */
    public function separateWithdraw(...$args)
    {
        return $this->transactionStatusService->separateWithdraw(...$args);
    }

    /** @deprecated Use TransactionStatusService::markAsNormalWithdraw() */
    public function markAsNormalWithdraw(...$args)
    {
        return $this->transactionStatusService->markAsNormalWithdraw(...$args);
    }

    /** @deprecated Use TransactionLockService::supportLockingLogics() */
    public function supportLockingLogics(...$args)
    {
        return $this->transactionLockService->supportLockingLogics(...$args);
    }

    /** @deprecated Use TransactionLockService::lock() */
    public function lock(...$args)
    {
        return $this->transactionLockService->lock(...$args);
    }

    /** @deprecated Use TransactionLockService::unlock() */
    public function unlock(...$args)
    {
        return $this->transactionLockService->unlock(...$args);
    }
}

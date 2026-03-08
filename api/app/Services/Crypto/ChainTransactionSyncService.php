<?php

namespace App\Services\Crypto;

use App\Models\ChainTransaction;
use App\Models\Channel;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ChainTransactionSyncService
{
    public function __construct(
        private readonly ChainTransactionMatchService $matchService,
    ) {}

    /**
     * 從 CryptoMonitorService 輪詢中順便存入鏈上交易
     * 不增加額外 API 呼叫
     */
    public function processFromPolling(Collection $chainTxDtos, UserChannelAccount $account): void
    {
        foreach ($chainTxDtos as $dto) {
            $this->upsertTransaction([
                'tx_hash' => $dto->txHash,
                'from' => $dto->from,
                'to' => $dto->to,
                'amount' => $dto->amount,
                'block_timestamp' => $dto->timestamp,
                'block_number' => null,
                'raw' => null,
            ], $account);
        }
    }

    /**
     * 同步單一帳號的最近交易（補漏排程用）
     */
    public function syncRecentTransactions(UserChannelAccount $account): int
    {
        $adapter = $this->resolveAdapter($account->chain_network ?? 'trc20');
        if (!$adapter) {
            return 0;
        }

        $address = $this->getAccountAddress($account);
        if (!$address) {
            return 0;
        }

        $result = $adapter->fetchTransactionHistory($address, 200);
        $count = 0;

        foreach ($result['data'] as $txData) {
            if ($this->upsertTransaction($txData, $account)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 同步所有 USDT 帳號
     */
    public function syncAllAccounts(): array
    {
        $accounts = UserChannelAccount::where('channel_code', Channel::CODE_USDT)
            ->whereNull('deleted_at')
            ->get();

        $totalSynced = 0;
        $totalAccounts = $accounts->count();

        foreach ($accounts as $account) {
            try {
                $synced = $this->syncRecentTransactions($account);
                $totalSynced += $synced;
            } catch (\Exception $e) {
                Log::error('ChainTransactionSyncService: 同步帳號失敗', [
                    'account_id' => $account->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return ['synced' => $totalSynced, 'accounts' => $totalAccounts];
    }

    /**
     * 歷史回溯：拉取指定天數內的交易
     */
    public function backfillHistory(UserChannelAccount $account, int $days = 30): int
    {
        $adapter = $this->resolveAdapter($account->chain_network ?? 'trc20');
        if (!$adapter) {
            return 0;
        }

        $address = $this->getAccountAddress($account);
        if (!$address) {
            return 0;
        }

        $minTimestamp = (string) (now()->subDays($days)->timestamp * 1000);
        $fingerprint = null;
        $totalCount = 0;

        do {
            $result = $adapter->fetchTransactionHistory($address, 200, $fingerprint, $minTimestamp);

            foreach ($result['data'] as $txData) {
                if ($this->upsertTransaction($txData, $account)) {
                    $totalCount++;
                }
            }

            $fingerprint = $result['fingerprint'];

            if ($result['data']->isEmpty()) {
                break;
            }
        } while ($fingerprint);

        return $totalCount;
    }

    /**
     * 解析並存入單筆鏈上交易，回傳是否為新記錄
     */
    private function upsertTransaction(array $txData, UserChannelAccount $account): bool
    {
        $address = $this->getAccountAddress($account);
        $toAddress = $txData['to'];
        $fromAddress = $txData['from'];

        // 判斷方向
        $direction = null;
        if (strtolower($toAddress) === strtolower($address)) {
            $direction = ChainTransaction::DIRECTION_IN;
        } elseif (strtolower($fromAddress) === strtolower($address)) {
            $direction = ChainTransaction::DIRECTION_OUT;
        } else {
            return false;
        }

        // 轉換 block_timestamp（毫秒 → Carbon）
        $blockTimestamp = $txData['block_timestamp'];
        if (is_numeric($blockTimestamp) && $blockTimestamp > 1e12) {
            $blockTimestamp = \Carbon\Carbon::createFromTimestampMs($blockTimestamp);
        } elseif (is_numeric($blockTimestamp)) {
            $blockTimestamp = \Carbon\Carbon::createFromTimestamp($blockTimestamp);
        }

        $chainTx = ChainTransaction::updateOrCreate(
            ['tx_hash' => $txData['tx_hash']],
            [
                'user_channel_account_id' => $account->id,
                'direction' => $direction,
                'from_address' => $fromAddress,
                'to_address' => $toAddress,
                'amount' => $txData['amount'],
                'block_number' => $txData['block_number'] ?? null,
                'block_timestamp' => $blockTimestamp,
                'confirmations' => 1,
                'raw_data' => $txData['raw'] ?? null,
            ]
        );

        // 只對新建立的記錄嘗試比對
        if ($chainTx->wasRecentlyCreated && $chainTx->match_status === ChainTransaction::STATUS_PENDING) {
            $this->matchService->matchTransaction($chainTx);
        }

        return $chainTx->wasRecentlyCreated;
    }

    private function getAccountAddress(UserChannelAccount $account): ?string
    {
        $detail = $account->detail;
        if (is_string($detail)) {
            $detail = json_decode($detail, true);
        }
        return $detail['wallet_address'] ?? $detail['account'] ?? $account->account ?? null;
    }

    private function resolveAdapter(string $network): ?ChainAdapterInterface
    {
        return match ($network) {
            'trc20' => app(Trc20Adapter::class),
            default => null,
        };
    }
}

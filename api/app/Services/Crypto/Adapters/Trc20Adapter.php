<?php

namespace App\Services\Crypto\Adapters;

use App\Services\Crypto\DTO\ChainTransaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Trc20Adapter implements ChainAdapterInterface
{
    // USDT TRC-20 合約地址 (Mainnet)
    private const USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    private const TRONGRID_BASE_URL = 'https://api.trongrid.io';

    public function fetchIncomingTransactions(string $address, ?string $sinceTimestamp = null): Collection
    {
        try {
            $params = [
                'only_to' => 'true',
                'contract_address' => self::USDT_CONTRACT,
                'limit' => 20,
                'order_by' => 'block_timestamp,desc',
            ];

            if ($sinceTimestamp) {
                $params['min_timestamp'] = $sinceTimestamp;
            }

            $response = Http::timeout(10)
                ->get(self::TRONGRID_BASE_URL . "/v1/accounts/{$address}/transactions/trc20", $params);

            if (!$response->successful()) {
                Log::warning('Trc20Adapter: TronGrid API 請求失敗', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);
                return collect();
            }

            $data = $response->json('data', []);

            return collect($data)
                ->filter(fn ($tx) => ($tx['token_info']['address'] ?? '') === self::USDT_CONTRACT)
                ->map(function ($tx) {
                    $decimals = (int) ($tx['token_info']['decimals'] ?? 6);
                    $rawAmount = $tx['value'] ?? '0';
                    $amount = bcdiv($rawAmount, bcpow('10', (string) $decimals), 6);

                    return new ChainTransaction(
                        txHash: $tx['transaction_id'],
                        from: $tx['from'],
                        to: $tx['to'],
                        amount: $amount,
                        timestamp: (int) ($tx['block_timestamp'] ?? 0),
                        confirmations: 1, // TronGrid 回傳的交易已確認
                    );
                });
        } catch (\Exception $e) {
            Log::error('Trc20Adapter: 發生例外', [
                'address' => $address,
                'exception' => $e->getMessage(),
            ]);
            return collect();
        }
    }
}

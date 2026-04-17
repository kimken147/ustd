<?php

namespace App\Services\Crypto\EnergyRental;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TronZapProvider implements EnergyRentalProviderInterface
{
    private string $apiToken;
    private string $apiSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiToken = config('services.energy_rental.tronzap.api_token', '');
        $this->apiSecret = config('services.energy_rental.tronzap.api_secret', '');
        $this->baseUrl = config('services.energy_rental.tronzap.base_url', 'https://api.tronzap.com');
    }

    public function delegateEnergy(string $receiveAddress, int $amount): array
    {
        $body = [
            'service' => 'energy',
            'params' => [
                'address' => $receiveAddress,
                'energy_amount' => $amount,
                'amount' => $amount,
                'duration' => 1,
            ],
        ];

        $result = $this->request('POST', '/v1/transaction/new', $body);

        return [
            'order_id' => $result['id'] ?? '',
            'paid_trx' => (string) ($result['cost'] ?? '0'),
            'success'  => true,
            'hash'     => $result['hash'] ?? null,
        ];
    }

    public function checkOrder(string $orderId): ?array
    {
        try {
            $result = $this->request('POST', '/v1/transaction/check', [
                'id' => $orderId,
            ]);

            return [
                'success' => ($result['status'] ?? '') === 'completed',
                'energy'  => (int) ($result['energy'] ?? 0),
                'cost'    => (string) ($result['cost'] ?? '0'),
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getBalance(): string
    {
        try {
            $result = $this->request('POST', '/v1/balance', []);
            return (string) ($result['balance'] ?? '0');
        } catch (\Throwable $e) {
            return '0';
        }
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiToken) && !empty($this->apiSecret);
    }

    public function name(): string
    {
        return 'tronzap';
    }

    private function request(string $method, string $endpoint, array $params): array
    {
        $requestBody = json_encode($params);
        $signature = hash('sha256', $requestBody . $this->apiSecret);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
            'X-Signature'   => $signature,
            'Content-Type'  => 'application/json',
        ])->timeout(15)->send($method, $this->baseUrl . $endpoint, [
            'body' => $requestBody,
        ]);

        if (!$response->successful()) {
            $body = $response->body();
            Log::error('TronZapProvider: API request failed', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
                'body'     => $body,
            ]);
            throw new \RuntimeException("TronZap API failed: HTTP {$response->status()} - {$body}");
        }

        $data = $response->json();
        $code = $data['code'] ?? -1;

        if ($code !== 0) {
            $error = $data['error'] ?? 'Unknown error';
            Log::error('TronZapProvider: API error', [
                'endpoint' => $endpoint,
                'code'     => $code,
                'error'    => $error,
            ]);
            throw new \RuntimeException("TronZap API error: [{$code}] {$error}");
        }

        return $data['result'] ?? [];
    }
}

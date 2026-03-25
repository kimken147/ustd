<?php

namespace App\Services\Crypto\EnergyRental;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NettsProvider implements EnergyRentalProviderInterface
{
    private string $apiKey;
    private string $whitelistedIp;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.energy_rental.netts.api_key', '');
        $this->whitelistedIp = config('services.energy_rental.netts.whitelisted_ip', '');
        $this->baseUrl = config('services.energy_rental.netts.base_url', 'https://netts.io');
    }

    public function delegateEnergy(string $receiveAddress, int $amount): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-API-KEY'    => $this->apiKey,
            'X-Real-IP'    => $this->whitelistedIp,
        ])->timeout(15)->post("{$this->baseUrl}/apiv2/order5m", [
            'amount'         => $amount,
            'receiveAddress' => $receiveAddress,
        ]);

        if (!$response->successful()) {
            $body = $response->body();
            Log::error('NettsProvider: delegateEnergy failed', [
                'status'  => $response->status(),
                'body'    => $body,
                'address' => $receiveAddress,
                'amount'  => $amount,
            ]);
            throw new \RuntimeException("Energy delegation failed: HTTP {$response->status()} - {$body}");
        }

        $data = $response->json('detail.data', []);
        $code = $response->json('detail.code', 0);

        if ($code !== 10000) {
            $msg = $response->json('detail.msg', 'Unknown error');
            Log::error('NettsProvider: delegateEnergy API error', [
                'code' => $code,
                'msg'  => $msg,
            ]);
            throw new \RuntimeException("Energy delegation failed: [{$code}] {$msg}");
        }

        return [
            'order_id' => $data['orderId'] ?? '',
            'paid_trx' => (string) ($data['paidTRX'] ?? '0'),
            'success'  => true,
            'hash'     => $data['hash'] ?? null,
        ];
    }

    public function checkOrder(string $orderId): ?array
    {
        $response = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
        ])->timeout(10)->get("{$this->baseUrl}/apiv2/order_check", [
            'order_id' => $orderId,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $order = $response->json('order', []);

        return [
            'success' => $response->json('success', false),
            'energy'  => (int) ($order['energy'] ?? 0),
            'cost'    => (string) ($order['cost'] ?? '0'),
        ];
    }

    public function getBalance(): string
    {
        $response = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'X-Real-IP' => $this->whitelistedIp,
        ])->timeout(10)->get("{$this->baseUrl}/apiv2/userinfo");

        if (!$response->successful()) {
            return '0';
        }

        return (string) ($response->json('stats.balance') ?? '0');
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey) && !empty($this->whitelistedIp);
    }

    public function name(): string
    {
        return 'netts';
    }
}

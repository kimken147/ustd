<?php

namespace Tests\Unit\Services\Crypto\Adapters;

use App\Services\Crypto\Adapters\Trc20Adapter;
use App\Services\Crypto\DTO\ChainTransaction;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Trc20AdapterTest extends TestCase
{
    private Trc20Adapter $adapter;

    // Valid TRON address for testing (USDT contract address)
    private const VALID_ADDRESS = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new Trc20Adapter();
        // Ensure tests use mainnet contract address regardless of env
        config(['services.trongrid.usdt_contract' => self::VALID_ADDRESS]);
    }

    // ---------------------------------------------------------------
    // fetchIncomingTransactions
    // ---------------------------------------------------------------

    public function test_fetchIncomingTransactions_happy_path(): void
    {
        Http::fake([
            '*/v1/accounts/*/transactions/trc20*' => Http::response([
                'data' => [
                    [
                        'transaction_id' => 'abc123hash',
                        'from' => 'TSenderAddress123',
                        'to' => 'TReceiverAddress456',
                        'value' => '10000000',
                        'block_timestamp' => 1700000000000,
                        'token_info' => [
                            'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                            'decimals' => 6,
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->adapter->fetchIncomingTransactions('TReceiverAddress456');

        $this->assertCount(1, $result);
        $tx = $result->first();
        $this->assertInstanceOf(ChainTransaction::class, $tx);
        $this->assertEquals('abc123hash', $tx->txHash);
        $this->assertEquals('TSenderAddress123', $tx->from);
        $this->assertEquals('TReceiverAddress456', $tx->to);
        $this->assertEquals('10.000000', $tx->amount);
        $this->assertEquals(1700000000000, $tx->timestamp);
        $this->assertEquals(1, $tx->confirmations);
    }

    public function test_fetchIncomingTransactions_filters_non_usdt_contract(): void
    {
        Http::fake([
            '*/v1/accounts/*/transactions/trc20*' => Http::response([
                'data' => [
                    [
                        'transaction_id' => 'tx1',
                        'from' => 'TSender',
                        'to' => 'TReceiver',
                        'value' => '5000000',
                        'block_timestamp' => 1700000000000,
                        'token_info' => [
                            'address' => 'TFakeContractAddress',
                            'decimals' => 6,
                        ],
                    ],
                    [
                        'transaction_id' => 'tx2',
                        'from' => 'TSender',
                        'to' => 'TReceiver',
                        'value' => '5000000',
                        'block_timestamp' => 1700000000000,
                        'token_info' => [
                            'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                            'decimals' => 6,
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->adapter->fetchIncomingTransactions('TReceiver');

        $this->assertCount(1, $result);
        $this->assertEquals('tx2', $result->first()->txHash);
    }

    public function test_fetchIncomingTransactions_amount_precision(): void
    {
        Http::fake([
            '*/v1/accounts/*/transactions/trc20*' => Http::response([
                'data' => [
                    [
                        'transaction_id' => 'txPrecision',
                        'from' => 'TSender',
                        'to' => 'TReceiver',
                        'value' => '1500000',
                        'block_timestamp' => 1700000000000,
                        'token_info' => [
                            'address' => 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t',
                            'decimals' => 6,
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->adapter->fetchIncomingTransactions('TReceiver');

        $this->assertEquals('1.500000', $result->first()->amount);
    }

    public function test_fetchIncomingTransactions_api_failure_returns_empty(): void
    {
        Http::fake([
            '*/v1/accounts/*/transactions/trc20*' => Http::response(null, 500),
        ]);

        $result = $this->adapter->fetchIncomingTransactions('TReceiver');

        $this->assertCount(0, $result);
    }

    public function test_fetchIncomingTransactions_with_sinceTimestamp(): void
    {
        Http::fake([
            '*/v1/accounts/*/transactions/trc20*' => Http::response(['data' => []]),
        ]);

        $this->adapter->fetchIncomingTransactions('TReceiver', '1700000000000');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'min_timestamp=1700000000000');
        });
    }

    // ---------------------------------------------------------------
    // getTransactionInfo
    // ---------------------------------------------------------------

    public function test_getTransactionInfo_confirmed_success(): void
    {
        Http::fake([
            '*/walletsolidity/gettransactioninfobyid' => Http::response([
                'id' => 'abc123',
                'fee' => 5000000,
                'receipt' => [
                    'result' => 'SUCCESS',
                ],
            ]),
        ]);

        $result = $this->adapter->getTransactionInfo('abc123');

        $this->assertNotNull($result);
        $this->assertTrue($result['confirmed']);
        $this->assertTrue($result['success']);
        $this->assertEquals('5.000000', $result['fee']);
    }

    public function test_getTransactionInfo_confirmed_failure(): void
    {
        Http::fake([
            '*/walletsolidity/gettransactioninfobyid' => Http::response([
                'id' => 'abc123',
                'fee' => 3000000,
                'receipt' => [
                    'result' => 'OUT_OF_ENERGY',
                ],
            ]),
        ]);

        $result = $this->adapter->getTransactionInfo('abc123');

        $this->assertNotNull($result);
        $this->assertTrue($result['confirmed']);
        $this->assertFalse($result['success']);
    }

    public function test_getTransactionInfo_not_yet_confirmed(): void
    {
        Http::fake([
            '*/walletsolidity/gettransactioninfobyid' => Http::response([]),
        ]);

        $result = $this->adapter->getTransactionInfo('abc123');

        $this->assertNull($result);
    }

    public function test_getTransactionInfo_api_error(): void
    {
        Http::fake([
            '*/walletsolidity/gettransactioninfobyid' => Http::response(null, 500),
        ]);

        $result = $this->adapter->getTransactionInfo('abc123');

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // getNativeBalance
    // ---------------------------------------------------------------

    public function test_getNativeBalance_happy_path(): void
    {
        Http::fake([
            '*/wallet/getaccount' => Http::response([
                'balance' => 30000000,
            ]),
        ]);

        $result = $this->adapter->getNativeBalance(self::VALID_ADDRESS);

        $this->assertEquals('30.000000', $result);
    }

    public function test_getNativeBalance_api_failure_throws_exception(): void
    {
        Http::fake([
            '*/wallet/getaccount' => Http::response(null, 500),
        ]);

        $this->expectException(\RuntimeException::class);

        $this->adapter->getNativeBalance(self::VALID_ADDRESS);
    }

    // ---------------------------------------------------------------
    // getTokenBalance
    // ---------------------------------------------------------------

    public function test_getTokenBalance_happy_path(): void
    {
        // 500 USDT = 500000000 raw = 0x1DCD6500
        $hexResult = str_pad(dechex(500000000), 64, '0', STR_PAD_LEFT);

        Http::fake([
            '*/wallet/triggerconstantcontract' => Http::response([
                'constant_result' => [$hexResult],
            ]),
        ]);

        $result = $this->adapter->getTokenBalance(self::VALID_ADDRESS);

        $this->assertEquals('500.000000', $result);
    }

    public function test_getTokenBalance_zero_balance(): void
    {
        Http::fake([
            '*/wallet/triggerconstantcontract' => Http::response([
                'constant_result' => ['0'],
            ]),
        ]);

        $result = $this->adapter->getTokenBalance(self::VALID_ADDRESS);

        $this->assertEquals('0', $result);
    }

    // ---------------------------------------------------------------
    // base58ToHex (tested via ReflectionMethod)
    // ---------------------------------------------------------------

    public function test_base58ToHex_valid_address(): void
    {
        $method = new \ReflectionMethod(Trc20Adapter::class, 'base58ToHex');
        $method->setAccessible(true);

        // USDT contract address: TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t
        // Known hex: 41a614f803b6fd780986a42c78ec9c7f77e6ded13c
        $hex = $method->invoke($this->adapter, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');

        $this->assertStringStartsWith('41', $hex);
        $this->assertEquals(42, strlen($hex));
    }

    public function test_base58ToHex_invalid_checksum(): void
    {
        $method = new \ReflectionMethod(Trc20Adapter::class, 'base58ToHex');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid TRON address checksum/');

        // Corrupt a valid address by changing last character
        $method->invoke($this->adapter, 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6X');
    }
}

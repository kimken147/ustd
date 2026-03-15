<?php

namespace Tests\Unit\Services\Crypto\Adapters;

use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\EvmAdapter;
use Tests\TestCase;

class EvmAdapterTest extends TestCase
{
    public function test_can_instantiate_erc20_adapter(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $this->assertInstanceOf(EvmAdapter::class, $adapter);
    }

    public function test_can_instantiate_bep20_adapter(): void
    {
        $adapter = new EvmAdapter('bsc');
        $this->assertInstanceOf(EvmAdapter::class, $adapter);
    }

    public function test_service_container_bindings(): void
    {
        $erc20 = app('evm.adapter.erc20');
        $this->assertInstanceOf(EvmAdapter::class, $erc20);

        $bep20 = app('evm.adapter.bep20');
        $this->assertInstanceOf(EvmAdapter::class, $bep20);
    }

    /**
     * Test ERC-20 transfer ABI encoding using reflection.
     */
    public function test_encode_erc20_transfer(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'encodeErc20Transfer');

        $to = '0x' . str_repeat('ab', 20);
        $amount = '1000000'; // 1 USDT (6 decimals)
        $result = $method->invoke($adapter, $to, $amount);

        // function selector = a9059cbb
        $this->assertStringStartsWith('0xa9059cbb', $result);
        // Total: 0x (2) + selector (8) + address (64) + amount (64) = 138
        $this->assertEquals(138, strlen($result));
    }

    /**
     * Test that the adapter implements ChainAdapterInterface.
     */
    public function test_implements_chain_adapter_interface(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $this->assertInstanceOf(ChainAdapterInterface::class, $adapter);
    }
}

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

    // ─── RLP Encoding Tests ──────────────────────────────────────────────

    /**
     * RLP: single byte < 0x80 encodes as itself.
     * Per Ethereum Yellow Paper Appendix B: if byte b is in [0x00, 0x7f], RLP(b) = b.
     */
    public function test_rlp_encode_integer_single_byte(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeInteger');

        // 1 → 0x01 (single byte, value < 0x80)
        $this->assertEquals('01', $method->invoke($adapter, '01'));

        // 0x7f → itself
        $this->assertEquals('7f', $method->invoke($adapter, '7f'));
    }

    /**
     * RLP: zero encodes as empty string (0x80).
     */
    public function test_rlp_encode_integer_zero(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeInteger');

        // Zero (empty hex) → 0x80
        $this->assertEquals('80', $method->invoke($adapter, ''));

        // Leading zeros stripped → empty → 0x80
        $this->assertEquals('80', $method->invoke($adapter, '00'));
        $this->assertEquals('80', $method->invoke($adapter, '0000'));
    }

    /**
     * RLP: single byte >= 0x80 uses length prefix.
     */
    public function test_rlp_encode_integer_single_byte_above_0x80(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeInteger');

        // 0x80 → 0x81 0x80 (1 byte string with prefix)
        $this->assertEquals('8180', $method->invoke($adapter, '80'));

        // 0xff → 0x81 0xff
        $this->assertEquals('81ff', $method->invoke($adapter, 'ff'));
    }

    /**
     * RLP: multi-byte integers are length-prefixed.
     * 1024 = 0x0400 → 0x82 0x04 0x00
     */
    public function test_rlp_encode_integer_multibyte(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeInteger');

        // 0x0400 (1024) → 0x82 0x04 0x00
        $this->assertEquals('820400', $method->invoke($adapter, '0400'));
    }

    /**
     * RLP: integers strip leading zeros.
     * 0x000400 should encode same as 0x0400.
     */
    public function test_rlp_encode_integer_strips_leading_zeros(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeInteger');

        // Leading zeros stripped: 0x000400 → 0x0400 → 0x82 0x04 0x00
        $this->assertEquals('820400', $method->invoke($adapter, '000400'));
    }

    /**
     * RLP: rlpEncodeBytes preserves leading zeros (for addresses/data).
     */
    public function test_rlp_encode_bytes_preserves_leading_zeros(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeBytes');

        // Address with leading zero byte: 0x00ab...
        $hex = '00ab';
        // 2 bytes → 0x82 prefix
        $this->assertEquals('8200ab', $method->invoke($adapter, $hex));
    }

    /**
     * RLP: empty bytes encodes as 0x80.
     */
    public function test_rlp_encode_bytes_empty(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeBytes');

        $this->assertEquals('80', $method->invoke($adapter, ''));
    }

    /**
     * RLP: 20-byte Ethereum address encoding.
     * Address is raw bytes, 20 bytes → 0x94 prefix (0x80 + 20).
     */
    public function test_rlp_encode_bytes_ethereum_address(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeBytes');

        $address = 'dac17f958d2ee523a2206206994597c13d831ec7'; // 40 hex chars = 20 bytes
        $result = $method->invoke($adapter, $address);

        // 0x80 + 20 = 0x94
        $this->assertStringStartsWith('94', $result);
        $this->assertEquals('94' . $address, $result);
    }

    /**
     * RLP: short list encoding. List of single bytes.
     * Per spec: [0x01, 0x02] → 0xc2 0x01 0x02
     */
    public function test_rlp_encode_list_short(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $listMethod = new \ReflectionMethod($adapter, 'rlpEncodeList');
        $intMethod = new \ReflectionMethod($adapter, 'rlpEncodeInteger');

        $items = [
            $intMethod->invoke($adapter, '01'),  // 0x01
            $intMethod->invoke($adapter, '02'),  // 0x02
        ];

        $result = $listMethod->invoke($adapter, $items);

        // 0xc0 + 2 = 0xc2, then 01 02
        $this->assertEquals('c20102', $result);
    }

    /**
     * RLP: empty list encodes as 0xc0.
     */
    public function test_rlp_encode_list_empty(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeList');

        $this->assertEquals('c0', $method->invoke($adapter, []));
    }

    /**
     * RLP: known Ethereum test vector — "dog" = [0x64, 0x6f, 0x67]
     * RLP("dog") = 0x83 0x64 0x6f 0x67
     */
    public function test_rlp_encode_bytes_known_vector_dog(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeBytes');

        $dogHex = bin2hex('dog'); // 646f67
        $result = $method->invoke($adapter, $dogHex);

        // 3 bytes → 0x80 + 3 = 0x83
        $this->assertEquals('83646f67', $result);
    }

    /**
     * RLP: known Ethereum test vector — ["cat", "dog"]
     * RLP(["cat","dog"]) = 0xc8 0x83 cat 0x83 dog
     */
    public function test_rlp_encode_list_known_vector_cat_dog(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $listMethod = new \ReflectionMethod($adapter, 'rlpEncodeList');
        $bytesMethod = new \ReflectionMethod($adapter, 'rlpEncodeBytes');

        $cat = $bytesMethod->invoke($adapter, bin2hex('cat')); // 83636174
        $dog = $bytesMethod->invoke($adapter, bin2hex('dog')); // 83646f67

        $result = $listMethod->invoke($adapter, [$cat, $dog]);

        // Total payload = 4 + 4 = 8 bytes → 0xc0 + 8 = 0xc8
        $this->assertEquals('c88363617483646f67', $result);
    }

    /**
     * RLP: known test vector — empty string = 0x80
     */
    public function test_rlp_encode_known_vector_empty_string(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeBytes');

        $this->assertEquals('80', $method->invoke($adapter, ''));
    }

    /**
     * RLP: known test vector — integer 15 = 0x0f (single byte < 0x80)
     */
    public function test_rlp_encode_known_vector_integer_15(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'rlpEncodeInteger');

        $this->assertEquals('0f', $method->invoke($adapter, '0f'));
    }

    /**
     * intToHex: converts decimal to minimal hex representation.
     */
    public function test_int_to_hex(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $method = new \ReflectionMethod($adapter, 'intToHex');

        // 0 → empty string
        $this->assertEquals('', $method->invoke($adapter, '0'));
        $this->assertEquals('', $method->invoke($adapter, ''));

        // 1 → "01"
        $this->assertEquals('01', $method->invoke($adapter, '1'));

        // 255 → "ff"
        $this->assertEquals('ff', $method->invoke($adapter, '255'));

        // 256 → "0100"
        $this->assertEquals('0100', $method->invoke($adapter, '256'));

        // Large gas price: 50 gwei = 50000000000
        $result = $method->invoke($adapter, '50000000000');
        $this->assertEquals('0ba43b7400', $result);
    }

    /**
     * RLP: encoding a typical EIP-155 unsigned transaction structure.
     * Verify the structure is correct by checking the list prefix and contents.
     */
    public function test_rlp_encode_eip155_transaction_structure(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $listMethod = new \ReflectionMethod($adapter, 'rlpEncodeList');
        $intMethod = new \ReflectionMethod($adapter, 'rlpEncodeInteger');
        $bytesMethod = new \ReflectionMethod($adapter, 'rlpEncodeBytes');

        // Typical tx: nonce=9, gasPrice=20gwei, gasLimit=21000, to=address, value=1eth, data='', chainId=1
        $items = [
            $intMethod->invoke($adapter, '09'),                                   // nonce = 9
            $intMethod->invoke($adapter, '04a817c800'),                          // gasPrice = 20 gwei
            $intMethod->invoke($adapter, '5208'),                                 // gasLimit = 21000
            $bytesMethod->invoke($adapter, '3535353535353535353535353535353535353535'), // to address (20 bytes)
            $intMethod->invoke($adapter, '0de0b6b3a7640000'),                    // value = 1 ETH in wei
            $bytesMethod->invoke($adapter, ''),                                    // data = empty
            $intMethod->invoke($adapter, '01'),                                   // chainId = 1
            $intMethod->invoke($adapter, ''),                                     // 0
            $intMethod->invoke($adapter, ''),                                     // 0
        ];

        $result = $listMethod->invoke($adapter, $items);

        // Should start with list prefix (0xc0-0xf7 for < 56 bytes)
        $firstByte = hexdec(substr($result, 0, 2));
        $this->assertGreaterThanOrEqual(0xc0, $firstByte);
        $this->assertLessThanOrEqual(0xf7, $firstByte);

        // Verify it contains the nonce (09) and gas limit (5208) in the output
        $this->assertStringContainsString('09', $result);
        $this->assertStringContainsString('5208', $result);

        // Verify the result is valid hex (even length)
        $this->assertEquals(0, strlen($result) % 2);
    }

    /**
     * RLP: known Ethereum test vector for EIP-155 signing.
     * From EIP-155 spec: nonce=9, gasPrice=20gwei, gasLimit=21000,
     * to=0x3535353535353535353535353535353535353535, value=10^18, data='', chainId=1
     * Expected unsigned tx hash: 0xdaf5a779ae972f972197303d7b574746c7ef83eadac0f2791ad23db92e4c8e53
     */
    public function test_rlp_encode_eip155_known_hash(): void
    {
        $adapter = new EvmAdapter('ethereum');
        $listMethod = new \ReflectionMethod($adapter, 'rlpEncodeList');
        $intMethod = new \ReflectionMethod($adapter, 'rlpEncodeInteger');
        $bytesMethod = new \ReflectionMethod($adapter, 'rlpEncodeBytes');

        // EIP-155 test vector
        $items = [
            $intMethod->invoke($adapter, '09'),                                   // nonce = 9
            $intMethod->invoke($adapter, '04a817c800'),                          // gasPrice = 20 gwei
            $intMethod->invoke($adapter, '5208'),                                 // gasLimit = 21000
            $bytesMethod->invoke($adapter, '3535353535353535353535353535353535353535'), // to
            $intMethod->invoke($adapter, '0de0b6b3a7640000'),                    // value = 1 ETH
            $bytesMethod->invoke($adapter, ''),                                    // data = empty
            $intMethod->invoke($adapter, '01'),                                   // chainId = 1
            $intMethod->invoke($adapter, ''),                                     // 0
            $intMethod->invoke($adapter, ''),                                     // 0
        ];

        $rlpHex = $listMethod->invoke($adapter, $items);

        // Keccak-256 the RLP-encoded unsigned tx
        $hash = \kornrunner\Keccak::hash(hex2bin($rlpHex), 256);

        // Known hash from EIP-155 specification
        $this->assertEquals(
            'daf5a779ae972f972197303d7b574746c7ef83eadac0f2791ad23db92e4c8e53',
            $hash,
            'EIP-155 unsigned transaction hash must match specification test vector'
        );
    }
}

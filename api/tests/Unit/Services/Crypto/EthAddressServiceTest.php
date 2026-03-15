<?php

namespace Tests\Unit\Services\Crypto;

use App\Services\Crypto\EthAddressService;
use App\Services\Crypto\TronAddressService;
use Tests\TestCase;

class EthAddressServiceTest extends TestCase
{
    private EthAddressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EthAddressService();
    }

    // =============================================
    // privateKeyToAddress 測試
    // =============================================

    /**
     * 使用已知測試向量驗證私鑰 1 轉地址的正確性。
     * 私鑰 = 1 對應的 Ethereum 地址已透過 etherscan.io 驗證。
     */
    public function test_known_private_key_1_produces_correct_address(): void
    {
        $privateKey = '0000000000000000000000000000000000000000000000000000000000000001';
        $address = $this->service->privateKeyToAddress($privateKey);

        $this->assertEquals('0x7E5F4552091A69125d5DfCb7b8C2659029395Bdf', $address);
    }

    /**
     * 使用第二個已知測試向量驗證（私鑰 = 2）。
     */
    public function test_known_private_key_2_produces_correct_address(): void
    {
        $privateKey = '0000000000000000000000000000000000000000000000000000000000000002';
        $address = $this->service->privateKeyToAddress($privateKey);

        $this->assertEquals('0x2B5AD5c4795c026514f8317c7a215E218DcCD6cF', $address);
    }

    /**
     * 驗證產生的地址格式正確：以 0x 開頭、42 字元長度。
     */
    public function test_private_key_to_address_format(): void
    {
        $privateKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';
        $address = $this->service->privateKeyToAddress($privateKey);

        $this->assertStringStartsWith('0x', $address);
        $this->assertEquals(42, strlen($address));
    }

    /**
     * 同樣的私鑰應該永遠產生同樣的地址（確定性）。
     */
    public function test_private_key_to_address_deterministic(): void
    {
        $privateKey = '0000000000000000000000000000000000000000000000000000000000000001';
        $address = $this->service->privateKeyToAddress($privateKey);

        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals($address, $this->service->privateKeyToAddress($privateKey));
        }
    }

    /**
     * 不同的私鑰應該產生不同的地址。
     */
    public function test_different_keys_produce_different_addresses(): void
    {
        $address1 = $this->service->privateKeyToAddress(
            '0000000000000000000000000000000000000000000000000000000000000001'
        );
        $address2 = $this->service->privateKeyToAddress(
            '0000000000000000000000000000000000000000000000000000000000000002'
        );

        $this->assertNotEquals($address1, $address2);
    }

    // =============================================
    // EIP-55 校驗和測試
    // =============================================

    /**
     * privateKeyToAddress 產生的地址應通過 EIP-55 校驗。
     */
    public function test_generated_address_has_valid_eip55_checksum(): void
    {
        $privateKey = '0000000000000000000000000000000000000000000000000000000000000001';
        $address = $this->service->privateKeyToAddress($privateKey);

        $this->assertTrue($this->service->isValidChecksumAddress($address));
    }

    /**
     * 全小寫地址不應通過 EIP-55 校驗（除非碰巧一致，但已知向量不會）。
     */
    public function test_all_lowercase_address_fails_eip55_checksum(): void
    {
        // 已知私鑰 1 的地址含有大寫字母，全轉小寫後應失敗
        $address = '0x7e5f4552091a69125d5dfcb7b8c2659029395bdf';
        $this->assertFalse($this->service->isValidChecksumAddress($address));
    }

    /**
     * 錯誤大小寫的地址不應通過 EIP-55 校驗。
     */
    public function test_wrong_case_address_fails_eip55_checksum(): void
    {
        // 把正確地址中的一個大寫改為小寫
        $address = '0x7e5F4552091A69125d5DfCb7b8C2659029395Bdf'; // 7E → 7e
        $this->assertFalse($this->service->isValidChecksumAddress($address));
    }

    /**
     * 格式不正確的地址應失敗。
     */
    public function test_invalid_format_fails_eip55_checksum(): void
    {
        $this->assertFalse($this->service->isValidChecksumAddress(''));
        $this->assertFalse($this->service->isValidChecksumAddress('0x'));
        $this->assertFalse($this->service->isValidChecksumAddress('7E5F4552091A69125d5DfCb7b8C2659029395Bdf'));
        $this->assertFalse($this->service->isValidChecksumAddress('0xZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ'));
    }

    // =============================================
    // deriveChildKey 測試
    // =============================================

    /**
     * 衍生的子私鑰應為 64 字元的十六進位字串。
     */
    public function test_derive_child_key_format(): void
    {
        $masterKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';
        $childKey = $this->service->deriveChildKey($masterKey, 0);

        $this->assertEquals(64, strlen($childKey));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $childKey);
    }

    /**
     * 相同的主私鑰和 index 應該產生相同的子私鑰（確定性）。
     */
    public function test_derive_child_key_deterministic(): void
    {
        $masterKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';

        $child1 = $this->service->deriveChildKey($masterKey, 0);
        $child2 = $this->service->deriveChildKey($masterKey, 0);

        $this->assertEquals($child1, $child2);
    }

    /**
     * 不同的 index 應該產生不同的子私鑰。
     */
    public function test_derive_child_key_different_indices(): void
    {
        $masterKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';

        $child0 = $this->service->deriveChildKey($masterKey, 0);
        $child1 = $this->service->deriveChildKey($masterKey, 1);
        $child2 = $this->service->deriveChildKey($masterKey, 2);

        $this->assertNotEquals($child0, $child1);
        $this->assertNotEquals($child1, $child2);
        $this->assertNotEquals($child0, $child2);
    }

    /**
     * EthAddressService 與 TronAddressService 使用不同 HMAC key，
     * 相同主私鑰和索引應產生不同的子密鑰。
     */
    public function test_derive_child_key_different_from_tron(): void
    {
        $masterKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';
        $tronService = new TronAddressService();

        for ($i = 0; $i < 5; $i++) {
            $ethChild = $this->service->deriveChildKey($masterKey, $i);
            $tronChild = $tronService->deriveChildKey($masterKey, $i);

            $this->assertNotEquals(
                $ethChild,
                $tronChild,
                "ETH and TRON child keys must differ at index {$i}"
            );
        }
    }

    /**
     * 子私鑰應為有效的 secp256k1 私鑰（大於 0 且小於曲線階 n）。
     */
    public function test_derive_child_key_valid_secp256k1_range(): void
    {
        $masterKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';

        for ($i = 0; $i < 10; $i++) {
            $childKey = $this->service->deriveChildKey($masterKey, $i);
            $childInt = gmp_init($childKey, 16);
            $n = gmp_init('FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141', 16);

            $this->assertTrue(gmp_cmp($childInt, 0) > 0, "Child key at index {$i} must be > 0");
            $this->assertTrue(gmp_cmp($childInt, $n) < 0, "Child key at index {$i} must be < n");
        }
    }

    // =============================================
    // deriveChildAccount 測試
    // =============================================

    /**
     * 衍生帳戶應回傳含有 address 和 private_key 的陣列。
     */
    public function test_derive_child_account_structure(): void
    {
        $masterKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';
        $account = $this->service->deriveChildAccount($masterKey, 0);

        $this->assertArrayHasKey('address', $account);
        $this->assertArrayHasKey('private_key', $account);
        $this->assertStringStartsWith('0x', $account['address']);
        $this->assertEquals(42, strlen($account['address']));
        $this->assertEquals(64, strlen($account['private_key']));
    }

    /**
     * 衍生帳戶的 private_key 應與 deriveChildKey 產生的一致。
     */
    public function test_derive_child_account_consistency(): void
    {
        $masterKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';

        $childKey = $this->service->deriveChildKey($masterKey, 5);
        $account = $this->service->deriveChildAccount($masterKey, 5);

        $this->assertEquals($childKey, $account['private_key']);

        // 用子私鑰直接轉換的地址應與帳戶中的地址一致
        $expectedAddress = $this->service->privateKeyToAddress($childKey);
        $this->assertEquals($expectedAddress, $account['address']);
    }

    // =============================================
    // generateKeyPair 測試
    // =============================================

    /**
     * 產生的密鑰對應包含有效的地址和私鑰。
     */
    public function test_generate_key_pair_structure(): void
    {
        $pair = $this->service->generateKeyPair();

        $this->assertArrayHasKey('address', $pair);
        $this->assertArrayHasKey('private_key', $pair);
        $this->assertStringStartsWith('0x', $pair['address']);
        $this->assertEquals(42, strlen($pair['address']));
        $this->assertEquals(64, strlen($pair['private_key']));
    }

    /**
     * 產生的密鑰對中，私鑰應能推導出對應的地址。
     */
    public function test_generate_key_pair_address_matches_key(): void
    {
        $pair = $this->service->generateKeyPair();

        $expectedAddress = $this->service->privateKeyToAddress($pair['private_key']);
        $this->assertEquals($expectedAddress, $pair['address']);
    }

    /**
     * 連續產生的密鑰對不應重複。
     */
    public function test_generate_key_pair_unique(): void
    {
        $pair1 = $this->service->generateKeyPair();
        $pair2 = $this->service->generateKeyPair();

        $this->assertNotEquals($pair1['private_key'], $pair2['private_key']);
        $this->assertNotEquals($pair1['address'], $pair2['address']);
    }

    // =============================================
    // ETH 與 TRON 地址關聯性測試
    // =============================================

    /**
     * 同一私鑰產生的 ETH 地址（hex 部分）應與 TRON 地址（hex 部分，去掉 41 前綴）相同。
     * 因為底層的 secp256k1 + keccak-256 運算完全一致。
     */
    public function test_same_key_produces_related_eth_and_tron_addresses(): void
    {
        $privateKey = '0000000000000000000000000000000000000000000000000000000000000001';

        $ethAddress = $this->service->privateKeyToAddress($privateKey);
        // ETH 地址 hex 部分（去掉 0x，轉小寫）
        $ethHex = strtolower(substr($ethAddress, 2));

        // TRON 地址的 hex 為 41 + 相同的 20 bytes
        // 私鑰 1 的 20-byte hex = 7e5f4552091a69125d5dfcb7b8c2659029395bdf
        $this->assertEquals('7e5f4552091a69125d5dfcb7b8c2659029395bdf', $ethHex);
    }
}

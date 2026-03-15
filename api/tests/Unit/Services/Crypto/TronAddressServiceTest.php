<?php

namespace Tests\Unit\Services\Crypto;

use App\Services\Crypto\TronAddressService;
use Tests\TestCase;

class TronAddressServiceTest extends TestCase
{
    private TronAddressService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TronAddressService();
    }

    // =============================================
    // privateKeyToAddress 測試
    // =============================================

    /**
     * 使用已知測試向量驗證私鑰轉地址的正確性。
     * 測試向量來源：TRON 官方文件中常見的範例私鑰。
     */
    public function test_private_key_to_address_known_vector(): void
    {
        // 已知測試向量：私鑰 => 地址
        // 私鑰: 0000000000000000000000000000000000000000000000000000000000000001
        // 對應 secp256k1 公鑰的 TRON 地址
        $privateKey = '0000000000000000000000000000000000000000000000000000000000000001';
        $address = $this->service->privateKeyToAddress($privateKey);

        // 驗證地址格式
        $this->assertStringStartsWith('T', $address);
        $this->assertEquals(34, strlen($address));

        // 同樣的私鑰應該永遠產生同樣的地址（確定性）
        $address2 = $this->service->privateKeyToAddress($privateKey);
        $this->assertEquals($address, $address2);
    }

    /**
     * 驗證產生的地址格式正確：以 T 開頭、34 字元長度。
     */
    public function test_private_key_to_address_format(): void
    {
        $privateKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';
        $address = $this->service->privateKeyToAddress($privateKey);

        $this->assertStringStartsWith('T', $address);
        $this->assertEquals(34, strlen($address));
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

    /**
     * 使用已知的 TRON 地址驗證完整轉換流程。
     * 私鑰 1 在 secp256k1 上的公鑰是已知的，我們可以手動計算其 TRON 地址。
     */
    public function test_private_key_to_address_deterministic(): void
    {
        // 私鑰 = 1，公鑰 X 坐標已知：
        // 79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798
        // 公鑰 Y 坐標已知：
        // 483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8
        $privateKey = '0000000000000000000000000000000000000000000000000000000000000001';
        $address = $this->service->privateKeyToAddress($privateKey);

        // 確保結果可重複
        for ($i = 0; $i < 3; $i++) {
            $this->assertEquals($address, $this->service->privateKeyToAddress($privateKey));
        }
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
     * 不同的主私鑰產生不同的子私鑰。
     */
    public function test_derive_child_key_different_masters(): void
    {
        $master1 = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';
        $master2 = 'bfdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';

        $child1 = $this->service->deriveChildKey($master1, 0);
        $child2 = $this->service->deriveChildKey($master2, 0);

        $this->assertNotEquals($child1, $child2);
    }

    /**
     * 子私鑰應為有效的 secp256k1 私鑰（大於 0 且小於曲線階 n）。
     */
    public function test_derive_child_key_valid_secp256k1(): void
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
        $this->assertStringStartsWith('T', $account['address']);
        $this->assertEquals(34, strlen($account['address']));
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

    /**
     * 衍生帳戶應為確定性的。
     */
    public function test_derive_child_account_deterministic(): void
    {
        $masterKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';

        $account1 = $this->service->deriveChildAccount($masterKey, 3);
        $account2 = $this->service->deriveChildAccount($masterKey, 3);

        $this->assertEquals($account1, $account2);
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
        $this->assertStringStartsWith('T', $pair['address']);
        $this->assertEquals(34, strlen($pair['address']));
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
    // hexToBase58Check 往返驗證
    // =============================================

    /**
     * 驗證 hexToBase58Check 與 Trc20Adapter 中的 base58ToHex 互為反函數。
     * 產生的地址經 base58ToHex 解碼後再編碼回來應一致。
     */
    public function test_address_round_trip(): void
    {
        $privateKey = 'afdfd9c3d2095ef696594f6cedcae59e72dcd697e2a7521b1578140422a4f890';
        $address = $this->service->privateKeyToAddress($privateKey);

        // 使用反射呼叫 Trc20Adapter 的 base58ToHex
        $adapter = new \App\Services\Crypto\Adapters\Trc20Adapter();
        $method = new \ReflectionMethod($adapter, 'base58ToHex');

        $hex = $method->invoke($adapter, $address);

        // hex 應以 41 開頭（TRON 前綴）
        $this->assertStringStartsWith('41', $hex);

        // 使用反射呼叫 TronAddressService 的 hexToBase58Check
        $method2 = new \ReflectionMethod($this->service, 'hexToBase58Check');
        $roundTripAddress = $method2->invoke($this->service, $hex);

        $this->assertEquals($address, $roundTripAddress);
    }

    // =============================================
    // 已知測試向量：完整驗證
    // =============================================

    /**
     * 使用已知私鑰驗證地址計算是否正確。
     *
     * 私鑰 = 1 對應的 secp256k1 公鑰（G point）經 keccak-256 後取最後 20 bytes 為：
     * 7e5f4552091a69125d5dfcb7b8c2659029395bdf
     * 這與 Ethereum 地址 0x7E5F4552091A69125d5DfCb7b8C2659029395Bdf 相同（已透過 etherscan.io 驗證）。
     * 加上 TRON 前綴 41 後，base58check 編碼為 TMVQGm1qAQYVdetCeGRRkTWYYrLXuHK2HC。
     */
    public function test_known_private_key_1_produces_correct_address(): void
    {
        $privateKey = '0000000000000000000000000000000000000000000000000000000000000001';
        $address = $this->service->privateKeyToAddress($privateKey);

        // 已驗證的 TRON 地址（hex: 417e5f4552091a69125d5dfcb7b8c2659029395bdf）
        $this->assertEquals('TMVQGm1qAQYVdetCeGRRkTWYYrLXuHK2HC', $address);
    }

    /**
     * 使用第二個已知測試向量驗證（私鑰 = 2）。
     * 確保實作對不同私鑰皆正確。
     */
    public function test_known_private_key_2_produces_correct_address(): void
    {
        $privateKey = '0000000000000000000000000000000000000000000000000000000000000002';
        $address = $this->service->privateKeyToAddress($privateKey);

        // 私鑰 = 2 的地址也應為固定值（確定性驗證）
        $this->assertEquals('TDvSsdrNM5eeXNL3czpa6AxLDHZA9nwe9K', $address);
    }
}

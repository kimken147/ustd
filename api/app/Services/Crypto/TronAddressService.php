<?php

namespace App\Services\Crypto;

use Elliptic\EC;
use kornrunner\Keccak;

class TronAddressService
{
    /** secp256k1 曲線的階（order），用於子密鑰模運算 */
    private const SECP256K1_ORDER = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141';

    /** TRON 地址前綴（主網為 0x41） */
    private const TRON_ADDRESS_PREFIX = '41';

    /** Base58 字母表 */
    private const BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';

    /** HMAC-SHA512 衍生子密鑰時使用的金鑰 */
    private const CHILD_DERIVATION_KEY = 'tron-hd-child';

    private EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    /**
     * 將十六進位私鑰轉換為 TRON base58check 地址。
     *
     * 步驟：
     * 1. 用 secp256k1 取得未壓縮公鑰（65 bytes，04 開頭）
     * 2. 移除 04 前綴，對剩餘 64 bytes 做 keccak-256 雜湊
     * 3. 取雜湊結果最後 20 bytes
     * 4. 加上 TRON 前綴 41
     * 5. 編碼為 base58check
     */
    public function privateKeyToAddress(string $privateKeyHex): string
    {
        // 取得未壓縮公鑰（false = 不壓縮，'hex' = 輸出格式）
        $key = $this->ec->keyFromPrivate($privateKeyHex, 'hex');
        $publicKeyHex = $key->getPublic(false, 'hex');

        // 移除 04 前綴（未壓縮公鑰標記）
        $publicKeyWithoutPrefix = substr($publicKeyHex, 2);

        // Keccak-256 雜湊
        $hash = Keccak::hash(hex2bin($publicKeyWithoutPrefix), 256);

        // 取最後 20 bytes（40 個十六進位字元）
        $addressHex = substr($hash, -40);

        // 加上 TRON 前綴
        $fullAddressHex = self::TRON_ADDRESS_PREFIX . $addressHex;

        // 編碼為 base58check
        return $this->hexToBase58Check($fullAddressHex);
    }

    /**
     * 從主私鑰和索引衍生子私鑰。
     *
     * 使用 HMAC-SHA512 進行確定性衍生：
     * - data = 主私鑰 bytes + 索引 4-byte big-endian
     * - key = 'tron-hd-child'
     * - 取前 32 bytes 作為 modifier
     * - childKey = (masterKey + modifier) mod n
     */
    public function deriveChildKey(string $masterPrivateKeyHex, int $index): string
    {
        // 組合 HMAC 資料：主私鑰 bytes + index（4-byte big-endian）
        $masterBytes = hex2bin($masterPrivateKeyHex);
        $indexBytes = pack('N', $index); // 'N' = unsigned long, big-endian
        $data = $masterBytes . $indexBytes;

        // HMAC-SHA512 衍生
        $hmac = hash_hmac('sha512', $data, self::CHILD_DERIVATION_KEY);

        // 取前 32 bytes（64 個十六進位字元）作為 modifier
        $modifierHex = substr($hmac, 0, 64);

        // childKey = (masterKey + modifier) mod n
        $masterInt = gmp_init($masterPrivateKeyHex, 16);
        $modifierInt = gmp_init($modifierHex, 16);
        $n = gmp_init(self::SECP256K1_ORDER, 16);

        $childInt = gmp_mod(gmp_add($masterInt, $modifierInt), $n);

        // 確保子密鑰不為零（機率極低，但做防護）
        if (gmp_cmp($childInt, 0) === 0) {
            throw new \RuntimeException('衍生的子密鑰為零，請使用不同的索引');
        }

        // 回傳 64 字元十六進位字串，零填充
        return str_pad(gmp_strval($childInt, 16), 64, '0', STR_PAD_LEFT);
    }

    /**
     * 衍生子帳戶，回傳地址和私鑰。
     *
     * @return array{address: string, private_key: string}
     */
    public function deriveChildAccount(string $masterPrivateKeyHex, int $index): array
    {
        $childKey = $this->deriveChildKey($masterPrivateKeyHex, $index);

        return [
            'address' => $this->privateKeyToAddress($childKey),
            'private_key' => $childKey,
        ];
    }

    /**
     * 產生全新的隨機密鑰對。
     *
     * @return array{address: string, private_key: string}
     */
    public function generateKeyPair(): array
    {
        // 使用密碼學安全的隨機數產生 32 bytes 私鑰
        $privateKeyBytes = random_bytes(32);
        $privateKeyHex = bin2hex($privateKeyBytes);

        // 確保私鑰在有效範圍內（1 到 n-1）
        $n = gmp_init(self::SECP256K1_ORDER, 16);
        $keyInt = gmp_init($privateKeyHex, 16);

        // 若私鑰為零或大於等於 n，取模後加 1
        if (gmp_cmp($keyInt, 0) === 0 || gmp_cmp($keyInt, $n) >= 0) {
            $keyInt = gmp_add(gmp_mod($keyInt, gmp_sub($n, gmp_init(1))), gmp_init(1));
            $privateKeyHex = str_pad(gmp_strval($keyInt, 16), 64, '0', STR_PAD_LEFT);
        }

        return [
            'address' => $this->privateKeyToAddress($privateKeyHex),
            'private_key' => $privateKeyHex,
        ];
    }

    /**
     * 將 41 開頭的十六進位地址編碼為 base58check 格式。
     *
     * 步驟：
     * 1. 計算校驗碼：payload 的雙重 SHA-256 前 4 bytes
     * 2. 將校驗碼附加到 payload 後面
     * 3. 轉換為 base58 編碼
     * 4. 處理前導零位元組（每個變成 '1'）
     */
    private function hexToBase58Check(string $addressHex): string
    {
        // 計算校驗碼：雙重 SHA-256 的前 4 bytes
        $payload = hex2bin($addressHex);
        $checksum = hash('sha256', hash('sha256', $payload, true));
        $checksumHex = substr($checksum, 0, 8); // 前 4 bytes = 8 個十六進位字元

        // 完整資料 = payload + 校驗碼
        $fullHex = $addressHex . $checksumHex;
        $fullBytes = hex2bin($fullHex);

        // 計算前導零位元組數量
        $leadingZeros = 0;
        $len = strlen($fullBytes);
        for ($i = 0; $i < $len; $i++) {
            if (ord($fullBytes[$i]) === 0) {
                $leadingZeros++;
            } else {
                break;
            }
        }

        // 將完整十六進位轉為大整數，再轉為 base58
        $num = gmp_init($fullHex, 16);
        $base58 = '';
        $base = gmp_init(58);
        $zero = gmp_init(0);

        while (gmp_cmp($num, $zero) > 0) {
            [$num, $remainder] = gmp_div_qr($num, $base);
            $base58 = self::BASE58_ALPHABET[gmp_intval($remainder)] . $base58;
        }

        // 每個前導零位元組對應一個 '1'
        return str_repeat('1', $leadingZeros) . $base58;
    }
}

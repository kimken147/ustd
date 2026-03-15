<?php

namespace App\Services\Crypto;

use Elliptic\EC;
use kornrunner\Keccak;

class EthAddressService
{
    /** secp256k1 曲線的階（order），用於子密鑰模運算 */
    private const SECP256K1_ORDER = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141';

    /** HMAC-SHA512 衍生子密鑰時使用的金鑰（與 TRON 不同以產生不同的子密鑰） */
    private const CHILD_DERIVATION_KEY = 'ethereum-hd-child';

    private EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    /**
     * 將十六進位私鑰轉換為 EIP-55 校驗和的 Ethereum 地址。
     *
     * 步驟：
     * 1. 用 secp256k1 取得未壓縮公鑰（65 bytes，04 開頭）
     * 2. 移除 04 前綴，對剩餘 64 bytes 做 keccak-256 雜湊
     * 3. 取雜湊結果最後 20 bytes
     * 4. 加上 0x 前綴
     * 5. 套用 EIP-55 校驗和大小寫
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

        // 套用 EIP-55 校驗和並加上 0x 前綴
        return '0x' . $this->applyEip55Checksum($addressHex);
    }

    /**
     * 從主私鑰和索引衍生子私鑰。
     *
     * 使用 HMAC-SHA512 進行確定性衍生：
     * - data = 主私鑰 bytes + 索引 4-byte big-endian
     * - key = 'ethereum-hd-child'
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
     * 驗證地址是否為有效的 EIP-55 校驗和地址。
     *
     * 規則：
     * - 必須以 0x 開頭
     * - 長度為 42 字元
     * - 大小寫必須符合 EIP-55 校驗和
     */
    public function isValidChecksumAddress(string $address): bool
    {
        // 基本格式檢查
        if (strlen($address) !== 42 || substr($address, 0, 2) !== '0x') {
            return false;
        }

        $hexPart = substr($address, 2);

        // 必須是有效的十六進位字串
        if (! preg_match('/^[0-9a-fA-F]{40}$/', $hexPart)) {
            return false;
        }

        // 重新計算 EIP-55 校驗和並比較
        $checksummed = $this->applyEip55Checksum(strtolower($hexPart));

        return $hexPart === $checksummed;
    }

    /**
     * 套用 EIP-55 校驗和大小寫。
     *
     * 演算法：
     * 1. 取小寫十六進位地址（不含 0x）
     * 2. 對該 ASCII 字串計算 keccak-256 雜湊
     * 3. 對地址中的每個字元：若對應雜湊 nibble >= 8，則大寫
     */
    private function applyEip55Checksum(string $lowercaseHex): string
    {
        // 對小寫 hex 字串（ASCII）計算 keccak-256
        $hash = Keccak::hash($lowercaseHex, 256);

        $result = '';
        for ($i = 0; $i < 40; $i++) {
            $char = $lowercaseHex[$i];

            // 只有 a-f 需要處理大小寫
            if (ctype_alpha($char)) {
                // 取雜湊對應位置的 nibble 值
                $hashNibble = intval($hash[$i], 16);

                if ($hashNibble >= 8) {
                    $result .= strtoupper($char);
                } else {
                    $result .= $char;
                }
            } else {
                $result .= $char;
            }
        }

        return $result;
    }
}

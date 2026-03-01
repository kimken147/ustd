<?php

namespace Tests\Unit\Utils;

use App\Utils\SignatureCalculator;
use Tests\TestCase;

class SignatureCalculatorTest extends TestCase
{
    // ========== calculate() ==========

    public function test_calculate_basic(): void
    {
        $params = ['username' => 'merchant1', 'amount' => '100'];
        $secretKey = 'secret123';

        $expected = md5(urldecode(http_build_query(['amount' => '100', 'username' => 'merchant1']) . '&secret_key=secret123'));

        $this->assertEquals($expected, SignatureCalculator::calculate($params, $secretKey));
    }

    public function test_calculate_auto_sorts_params(): void
    {
        $secretKey = 'mysecret';

        $paramsA = ['z' => '1', 'a' => '2', 'm' => '3'];
        $paramsB = ['a' => '2', 'm' => '3', 'z' => '1'];
        $paramsC = ['m' => '3', 'z' => '1', 'a' => '2'];

        $signA = SignatureCalculator::calculate($paramsA, $secretKey);
        $signB = SignatureCalculator::calculate($paramsB, $secretKey);
        $signC = SignatureCalculator::calculate($paramsC, $secretKey);

        $this->assertEquals($signA, $signB);
        $this->assertEquals($signB, $signC);
    }

    public function test_calculate_empty_params(): void
    {
        $secretKey = 'secret';

        $result = SignatureCalculator::calculate([], $secretKey);

        $expected = md5(urldecode('&secret_key=secret'));
        $this->assertEquals($expected, $result);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result);
    }

    public function test_calculate_returns_32_char_hex_string(): void
    {
        $result = SignatureCalculator::calculate(['foo' => 'bar'], 'key');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result);
    }

    public function test_calculate_with_chinese_characters(): void
    {
        $params = ['name' => '張三', 'city' => '台北'];
        $secretKey = 'secret';

        $result = SignatureCalculator::calculate($params, $secretKey);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result);

        // Verify manually: urldecode undoes the URL encoding from http_build_query
        ksort($params);
        $expected = md5(urldecode(http_build_query($params) . '&secret_key=' . $secretKey));
        $this->assertEquals($expected, $result);
    }

    public function test_calculate_with_special_url_characters(): void
    {
        $params = ['url' => 'https://example.com?a=1&b=2', 'name' => 'foo bar'];
        $secretKey = 'key';

        $result = SignatureCalculator::calculate($params, $secretKey);

        ksort($params);
        $expected = md5(urldecode(http_build_query($params) . '&secret_key=' . $secretKey));
        $this->assertEquals($expected, $result);
    }

    public function test_calculate_different_secret_keys_produce_different_signs(): void
    {
        $params = ['amount' => '100'];

        $sign1 = SignatureCalculator::calculate($params, 'key1');
        $sign2 = SignatureCalculator::calculate($params, 'key2');

        $this->assertNotEquals($sign1, $sign2);
    }

    public function test_calculate_is_deterministic(): void
    {
        $params = ['order' => 'ORD001', 'amount' => '500'];
        $secretKey = 'fixed_secret';

        $sign1 = SignatureCalculator::calculate($params, $secretKey);
        $sign2 = SignatureCalculator::calculate($params, $secretKey);

        $this->assertEquals($sign1, $sign2);
    }

    // ========== verify() ==========

    public function test_verify_correct_sign_returns_true(): void
    {
        $params = ['username' => 'merchant1', 'amount' => '100'];
        $secretKey = 'secret123';

        $sign = SignatureCalculator::calculate($params, $secretKey);

        $this->assertTrue(SignatureCalculator::verify($params, $secretKey, $sign));
    }

    public function test_verify_wrong_sign_returns_false(): void
    {
        $params = ['username' => 'merchant1', 'amount' => '100'];
        $secretKey = 'secret123';

        $this->assertFalse(SignatureCalculator::verify($params, $secretKey, 'wrong_sign'));
    }

    public function test_verify_case_insensitive(): void
    {
        $params = ['username' => 'merchant1', 'amount' => '100'];
        $secretKey = 'secret123';

        $sign = SignatureCalculator::calculate($params, $secretKey);

        $this->assertTrue(SignatureCalculator::verify($params, $secretKey, strtoupper($sign)));
        $this->assertTrue(SignatureCalculator::verify($params, $secretKey, strtolower($sign)));

        // Mixed case
        $mixedCase = '';
        for ($i = 0; $i < strlen($sign); $i++) {
            $mixedCase .= $i % 2 === 0 ? strtoupper($sign[$i]) : strtolower($sign[$i]);
        }
        $this->assertTrue(SignatureCalculator::verify($params, $secretKey, $mixedCase));
    }

    public function test_verify_wrong_secret_key_returns_false(): void
    {
        $params = ['username' => 'merchant1'];
        $sign = SignatureCalculator::calculate($params, 'correct_key');

        $this->assertFalse(SignatureCalculator::verify($params, 'wrong_key', $sign));
    }

    public function test_verify_modified_params_returns_false(): void
    {
        $params = ['username' => 'merchant1', 'amount' => '100'];
        $secretKey = 'secret123';
        $sign = SignatureCalculator::calculate($params, $secretKey);

        $modifiedParams = ['username' => 'merchant1', 'amount' => '200'];

        $this->assertFalse(SignatureCalculator::verify($modifiedParams, $secretKey, $sign));
    }

    // ========== Consistency with NotifyTransactionTest ==========

    public function test_consistency_with_notify_transaction_sign_logic(): void
    {
        $secretKey = 'abc123def456secret';
        $mainData = [
            'order_number' => 'ORD999',
            'system_order_number' => 'SYS999',
            'username' => 'TESTMERCHANT',
            'amount' => '200.000000',
            'status' => 4,
            'chain_network' => 'trc20',
            'tx_hash' => '0xhash',
        ];

        // Old way (inline)
        $parameters = $mainData;
        ksort($parameters);
        $oldSign = md5(urldecode(http_build_query($parameters) . '&secret_key=' . $secretKey));

        // New way (SignatureCalculator)
        $newSign = SignatureCalculator::calculate($mainData, $secretKey);

        $this->assertEquals($oldSign, $newSign);
    }
}

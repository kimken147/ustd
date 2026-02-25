<?php

namespace Tests\Unit\Utils;

use App\Utils\UserUtil;
use Mockery;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class UserUtilTest extends TestCase
{
    // ---------------------------------------------------------------
    // generatePassword()
    // ---------------------------------------------------------------

    public function test_generate_password_returns_8_chars(): void
    {
        $util = new UserUtil();

        $password = $util->generatePassword();

        $this->assertSame(8, strlen($password));
    }

    public function test_generate_password_returns_unique_values(): void
    {
        $util = new UserUtil();

        $password1 = $util->generatePassword();
        $password2 = $util->generatePassword();

        $this->assertNotSame($password1, $password2);
    }

    // ---------------------------------------------------------------
    // generateSecretKey()
    // ---------------------------------------------------------------

    public function test_generate_secret_key_returns_32_chars(): void
    {
        $util = new UserUtil();

        $secretKey = $util->generateSecretKey();

        $this->assertSame(32, strlen($secretKey));
    }

    public function test_generate_secret_key_returns_unique_values(): void
    {
        $util = new UserUtil();

        $key1 = $util->generateSecretKey();
        $key2 = $util->generateSecretKey();

        $this->assertNotSame($key1, $key2);
    }

    // ---------------------------------------------------------------
    // generateGoogle2faSecret()
    // ---------------------------------------------------------------

    public function test_generate_google2fa_secret_delegates_to_library(): void
    {
        $google2FA = Mockery::mock(Google2FA::class);
        $google2FA->shouldReceive('generateSecretKey')
            ->once()
            ->andReturn('MOCKED_SECRET_KEY');

        $util = new UserUtil($google2FA);

        $result = $util->generateGoogle2faSecret();

        $this->assertSame('MOCKED_SECRET_KEY', $result);
    }
}

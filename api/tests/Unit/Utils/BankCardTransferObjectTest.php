<?php

namespace Tests\Unit\Utils;

use App\Models\UserChannelAccount;
use App\Utils\BankCardTransferObject;
use Tests\TestCase;

class BankCardTransferObjectTest extends TestCase
{
    private BankCardTransferObject $bankCardTO;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bankCardTO = new BankCardTransferObject();
    }

    public function test_plain_creates_transfer_object(): void
    {
        $result = $this->bankCardTO->plain('银行', '1234', '张三', '省', '市');

        $this->assertInstanceOf(BankCardTransferObject::class, $result);
        $this->assertSame('银行', $result->bankName);
        $this->assertSame('1234', $result->bankCardNumber);
        $this->assertSame('张三', $result->bankCardHolderName);
        $this->assertSame('省', $result->bankProvince);
        $this->assertSame('市', $result->bankCity);
    }

    public function test_toFromChannelAccount_with_address(): void
    {
        $to = $this->bankCardTO->plain('工商银行', '6222000000000001', '李四', '广东省', '深圳市');

        $data = $to->toFromChannelAccount(true);

        $this->assertArrayHasKey(UserChannelAccount::DETAIL_KEY_BANK_NAME, $data);
        $this->assertArrayHasKey(UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER, $data);
        $this->assertArrayHasKey(UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME, $data);
        $this->assertArrayHasKey(UserChannelAccount::DETAIL_KEY_BANK_PROVINCE, $data);
        $this->assertArrayHasKey(UserChannelAccount::DETAIL_KEY_BANK_CITY, $data);

        $this->assertSame('工商银行', $data[UserChannelAccount::DETAIL_KEY_BANK_NAME]);
        $this->assertSame('6222000000000001', $data[UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER]);
        $this->assertSame('李四', $data[UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME]);
        $this->assertSame('广东省', $data[UserChannelAccount::DETAIL_KEY_BANK_PROVINCE]);
        $this->assertSame('深圳市', $data[UserChannelAccount::DETAIL_KEY_BANK_CITY]);
    }

    public function test_toFromChannelAccount_without_address(): void
    {
        $to = $this->bankCardTO->plain('招商银行', '6225000000000002', '王五', '浙江省', '杭州市');

        $data = $to->toFromChannelAccount(false);

        $this->assertArrayHasKey(UserChannelAccount::DETAIL_KEY_BANK_NAME, $data);
        $this->assertArrayHasKey(UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER, $data);
        $this->assertArrayHasKey(UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME, $data);
        $this->assertArrayNotHasKey(UserChannelAccount::DETAIL_KEY_BANK_PROVINCE, $data);
        $this->assertArrayNotHasKey(UserChannelAccount::DETAIL_KEY_BANK_CITY, $data);

        $this->assertCount(3, $data);
    }
}

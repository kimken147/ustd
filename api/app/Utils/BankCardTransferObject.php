<?php


namespace App\Utils;


use App\Models\BankCard;
use App\Models\SystemBankCard;

class BankCardTransferObject
{

    /**
     * @var string
     */
    public $bankName;

    /**
     * @var string
     */
    public $bankCardNumber;

    /**
     * @var string
     */
    public $bankCardHolderName;

    /**
     * @var string
     */
    public $bankProvince;

    /**
     * @var string
     */
    public $bankCity;

    /**
     * @param  SystemBankCard|BankCard  $bankCard
     * @return BankCardTransferObject
     */
    public function model($bankCard)
    {
        $bankCardTransferObject = new self;

        $bankCardTransferObject->bankName = $bankCard->bank_name;
        $bankCardTransferObject->bankProvince = $bankCard->bank_province;
        $bankCardTransferObject->bankCity = $bankCard->bank_city;
        $bankCardTransferObject->bankCardNumber = $bankCard->bank_card_number;
        $bankCardTransferObject->bankCardHolderName = $bankCard->bank_card_holder_name;

        return $bankCardTransferObject;
    }

    /**
     * @param  string  $bankName
     * @param  string  $bankCardNumber
     * @param  string  $bankCardHolderName
     * @return BankCardTransferObject
     */
    public function plain(string $bankName, string $bankCardNumber, string $bankCardHolderName, string $bankProvince, string $bankCity)
    {
        $bankCardTransferObject = new self;

        $bankCardTransferObject->bankName = $bankName;
        $bankCardTransferObject->bankProvince = $bankProvince;
        $bankCardTransferObject->bankCity = $bankCity;
        $bankCardTransferObject->bankCardNumber = $bankCardNumber;
        $bankCardTransferObject->bankCardHolderName = $bankCardHolderName;

        return $bankCardTransferObject;
    }
}

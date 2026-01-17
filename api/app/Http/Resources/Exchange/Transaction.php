<?php

namespace App\Http\Resources\Exchange;

use App\Http\Resources\TransactionCertificateFileCollection;
use App\Models\TransactionCertificateFile;
use App\Models\UserChannelAccount;
use App\Utils\AmountDisplayTransformer;
use App\Utils\BCMathUtil;
use App\Utils\FakeCryptoExchange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class Transaction extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                    => $this->getKey(),
            'type'                  => $this->type,
            'system_order_number'   => $this->system_order_number,
            'amount'                => AmountDisplayTransformer::transform($this->floating_amount),
            // 暫時性設定，等前端更新後改回 amount
            'random_amounts'        => $this->randomAmounts($this->floating_amount),
            'status'                => $this->getStatus(),
            'matched_at'            => optional($this->matched_at)->toIso8601String(),
            'created_at'            => $this->created_at->toIso8601String(),
            'confirmed_at'          => optional($this->confirmed_at)->toIso8601String(),
            'floating_amount'       => AmountDisplayTransformer::transform($this->floating_amount),
            'from_channel_account'  => (object) $this->from_channel_account,
            'confirmable'           => $this->getConfirmable(),
            'cryptocurrency_amount' => $this->getCryptocurrencyAmount(),
            'certificate_file_path' => $this->temporaryUrl($this->certificate_file_path),
            'certificate_files'     => TransactionCertificateFileCollection::make($this->getCertificateFiles()),
        ];
    }

    private function randomAmounts($initialAmount)
    {
        /** @var BCMathUtil $bcMath */
        $bcMath = app(BCMathUtil::class);
        $minAmount = $bcMath->subMinZero($initialAmount, 500);
        $maxAmount = $bcMath->add($initialAmount, 500);
        $randomNumbers = collect([$initialAmount]);

        for ($i = 0; $i < 3; $i++) {
            $randomNumbers[] = rand($minAmount, $maxAmount);
        }

        return $randomNumbers->map(function ($amount) {
            return AmountDisplayTransformer::transform($amount);
        })->shuffle();
    }

    private function getStatus()
    {
        if ($this->type === \App\Model\Transaction::TYPE_PAUFEN_WITHDRAW) {
            if (in_array($this->status, [
                    \App\Model\Transaction::STATUS_SUCCESS, \App\Model\Transaction::STATUS_MANUAL_SUCCESS
                ]) && !$this->to_wallet_settled) {
                return \App\Model\Transaction::STATUS_PAYING;
            }

            if (in_array($this->status, [\App\Model\Transaction::STATUS_RECEIVED])) {
                return \App\Model\Transaction::STATUS_PAYING;
            }
        }

        return $this->status;
    }

    private function getConfirmable()
    {
        return (
            !$this->locked
            && $this->from_id === auth()->user()->getKey()
            && $this->status === \App\Model\Transaction::STATUS_PAYING
            && $this->type === \App\Model\Transaction::TYPE_PAUFEN_TRANSACTION
        );
    }

    private function getCryptocurrencyAmount()
    {
        /** @var BCMathUtil $bcMath */
        $bcMath = app(BCMathUtil::class);

        switch ($this->type) {
            case \App\Model\Transaction::TYPE_PAUFEN_TRANSACTION:
                $transactionFee = $this->transactionFees()->where('user_id', $this->from_id)->first();
                $profit = data_get($transactionFee, 'profit', '0.00');
                $cnyAmount = $bcMath->sub($this->floating_amount, $profit);
                break;
            case \App\Model\Transaction::TYPE_PAUFEN_WITHDRAW:
                $cnyAmount = $this->floating_amount;
                break;
            default:
                throw new RuntimeException();
        }

        if (!$this->fakeCryptoTransaction) {
            /** @var FakeCryptoExchange $fakeCryptoExchange */
            $fakeCryptoExchange = app(FakeCryptoExchange::class);

            return AmountDisplayTransformer::transform($fakeCryptoExchange->cnyToUsdt($cnyAmount)).' USDT';
        }

        return AmountDisplayTransformer::transform($this->fakeCryptoTransaction->amount).' USDT';
    }

    private function temporaryUrl($certificateFilePath)
    {
        if ($certificateFilePath) {
            try {
                return Storage::disk('transaction-certificate-files')->temporaryUrl($certificateFilePath,
                    now()->addHour());
            } catch (RuntimeException $ignored) {

            }
        }

        return $certificateFilePath;
    }

    private function getCertificateFiles()
    {
        if ($this->certificate_file_path) {
            $certificateFile = new TransactionCertificateFile(['path' => $this->certificate_file_path]);
            $certificateFile->id = 0;
            $certificateFile->created_at = now();
            $certificateFile->updated_at = $certificateFile->created_at;

            return collect([$certificateFile]);
        }

        return $this->whenLoaded('certificateFiles');
    }

    private function transformDetail($detail)
    {
        try {
            if ($qrCodeFilePath = data_get($detail,
                UserChannelAccount::DETAIL_KEY_QR_CODE_FILE_PATH)) {
                data_set(
                    $detail,
                    UserChannelAccount::DETAIL_KEY_QR_CODE_FILE_PATH,
                    Storage::disk('user-channel-accounts-qr-code')->temporaryUrl($qrCodeFilePath, now()->addHour())
                );
            }
        } catch (RuntimeException $e) {
            Log::debug($e);

            return $detail;
        }

        return $detail;
    }
}

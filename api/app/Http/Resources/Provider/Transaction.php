<?php

namespace App\Http\Resources\Provider;

use Carbon\Carbon;
use App\Model\UserChannelAccount;
use App\Utils\AmountDisplayTransformer;
use App\Utils\BCMathUtil;
use App\Model\TransactionFee;
use App\Model\Notification;
use App\Model\Channel;
use App\Http\Resources\User;
use App\Http\Resources\TransactionCertificateFileCollection;
use App\Model\TransactionCertificateFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $toChannel = $this->to_channel_account;
        return [
            'id'                               => $this->getKey(),
            'operator'                         => $this->operator ? [
                'id'   => $this->operator->getKey(),
                'name' => $this->operator->name,
            ] : null,
            'system_order_number'              => $this->system_order_number,
            'channel_code'                     => $this->channel->code,
            'channel_name'                     => $this->channel->name,
            'channel_present_result'           => $this->channel->present_result,
            'amount'                           => AmountDisplayTransformer::transform($this->floating_amount), // 暫時性設定，等前端更新後改回 amount
            'random_amounts'                   => $this->randomAmounts($this->floating_amount),
            'status'                           => $this->status,
            'matched_at'                       => optional($this->matched_at)->toIso8601String(),
            'created_at'                       => $this->created_at->toIso8601String(),
            'confirmed_at'                     => optional($this->confirmed_at)->toIso8601String(),
            'operated_at'                      => optional($this->operated_at)->toIso8601String(),
            'actual_amount'                    => AmountDisplayTransformer::transform($this->actual_amount),
            'floating_amount'                  => AmountDisplayTransformer::transform($this->floating_amount),
            'provider'                         => User::make($this->whenLoaded('from')),
            'provider_channel_account'         => array_merge($this->transformDetail($this->from_channel_account), [
                'note' => $this->fromChannelAccount->note ?? ''
            ]),
            'provider_device_name'             => $this->from_device_name,
            'confirmable'                      => $this->getConfirmable(),
            'provider_channel_account_hash_id' => $this->from_channel_account_hash_id,
            'provider_fees'                    => TransactionFeeCollection::make($this->whenLoaded('transactionFees',
                function () {
                    return $this->transactionFees
                        ->filter($this->filteredByRole(\App\Model\User::ROLE_PROVIDER))
                        ->filter($this->filterByDescent());
                })),
            'real_name'                        => data_get($toChannel, UserChannelAccount::DETAIL_KEY_REAL_NAME),
            'note'                             => $this->whenLoaded('channel', function () {
                return ($this->channel->note_enable && $this->note) ? $this->note : null;
            }),
            'client_ip'                        => $this->client_ipv4,
            'sms'                              => Notification::where('transaction_id',$this->getKey())->first()->notification ?? '',
            'lockable'                         => $this->getLockable(),
            'unlockable'                       => $this->getUnlockable(),
            'locked'                           => $this->locked,
            'locked_at'                        => optional($this->locked_at)->toIso8601String(),
            'locked_by'                        => $this->lockedBy ? [
                'id'   => $this->lockedBy->getKey(),
                'name' => $this->lockedBy->name,
            ] : null,
            'red_envelope_password'            => data_get($toChannel, 'red_envelope_password'),
            're_qq_qrcode_path'                => $this->temporaryQRcodeUrl(data_get($toChannel, 're_qq_qrcode_path')),
            'recorded_at'                      => data_get($toChannel, 'recorded_at') ? Carbon::parse(data_get($toChannel, 'recorded_at'))->toIso8601String() : null,
            'refunded_at'                      => optional($this->refunded_at)->toIso8601String(),
            'refunded_by'                      => $this->refundedBy ? [
                'id'   => $this->refundedBy->getKey(),
                'name' => $this->refundedBy->name,
            ] : null,
            'certificate_file_path'             => $this->temporaryUrl($this->certificate_file_path),
            'certificate_files'                 => TransactionCertificateFileCollection::make($this->getCertificateFiles()),
            'usdt_rate'                         => $this->usdt_rate
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

    private function getConfirmable()
    {
        return (
            !$this->locked
            && $this->from->isSelfOrDescendantOf(auth()->user())
            && $this->status === \App\Model\Transaction::STATUS_PAYING
            && $this->type === \App\Model\Transaction::TYPE_PAUFEN_TRANSACTION
            && ($this->channel_code != Channel::CODE_RE_ALIPAY || isset($this->to_channel_account['red_envelope_password'])) // 如果是口令红包，需要有口令才可以确认收款
        );
    }

    private function getLockable()
    {
        // 跑分提現只要被碼商搶到，一律從充值管理處理
        if ($this->type !== \App\Model\Transaction::TYPE_PAUFEN_TRANSACTION) {
            return false;
        }

        return !$this->locked;
    }

    private function getUnlockable()
    {
        if ($this->type !== \App\Model\Transaction::TYPE_PAUFEN_TRANSACTION) {
            return false;
        }

        if (!$this->locked) {
            return false;
        }

        if ($this->lockedBy->is(auth()->user()->realUser())) {
            return true;
        }

        if (optional($this->lockedBy->parent)->is(auth()->user()->realUser())) {
            return true;
        }

        return false;
    }

    private function filteredByRole(int $role)
    {
        return function (TransactionFee $transactionFee) use ($role) {
            if ($role === \App\Model\User::ROLE_ADMIN) {
                return $transactionFee->user_id === 0;
            }

            return optional($transactionFee->user)->role === $role;
        };
    }

    private function filterByDescent()
    {
        return function (TransactionFee $transactionFee) {
            return in_array($transactionFee->user_id, auth()->user()->descendants->pluck('id')->all());
        };
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

    private function temporaryQRcodeUrl($qrcodeFilePath)
    {

        if ($qrcodeFilePath) {
            try {
                return Storage::disk('user-channel-accounts-qr-code')->temporaryUrl($qrcodeFilePath,
                    now()->addHour());
            } catch (RuntimeException $ignored) {

            }
        }

        return $qrcodeFilePath;
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
}

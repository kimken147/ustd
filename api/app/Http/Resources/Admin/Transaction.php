<?php

namespace App\Http\Resources\Admin;

use Carbon\Carbon;
use App\Http\Resources\User;
use App\Models\TransactionFee;
use App\Models\UserChannelAccount;
use App\Models\Channel;
use App\Utils\BCMathUtil;
use App\Utils\AmountDisplayTransformer;
use App\Http\Resources\TransactionCertificateFileCollection;
use App\Models\TransactionCertificateFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
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

        $bcMath = app(BCMathUtil::class);

        $merchantTransactionFee = optional(
            $this->transactionFees
                ->filter(function ($fee) { return $fee->user_id == $this->to_id; })
                ->first()
        );

        return [
            'id'                               => $this->getKey(),
            'operator'                         => $this->operator ? [
                'id'   => $this->operator->getKey(),
                'name' => $this->operator->name,
            ] : null,
            'merchant'                         => User::make($this->whenLoaded('to')),
            'system_order_number'              => $this->system_order_number,
            'parent_system_order_number'       => optional($this->parent)->system_order_number,
            'child_system_order_number'        => optional($this->child)->system_order_number,
            'child_id'                         => optional($this->child)->getKey(),
            'is_partial_success'               => !empty($this->parent) || !empty($this->child),
            'order_number'                     => $this->order_number,
            'channel_name'                     => $this->channel->name,
            'channel_code'                     => $this->channel_code,
            'amount'                           => AmountDisplayTransformer::transform($this->amount),
            'status'                           => $this->status,
            'notify_status'                    => $this->notify_status,
            'bug_report'                       => $this->bug_report,
            'note_exist'                       => $this->relationLoaded('transactionNotes') && $this->transactionNotes->isNotEmpty(),
            'matched_at'                       => optional($this->matched_at)->toIso8601String(),
            'created_at'                       => $this->created_at->toIso8601String(),
            'confirmed_at'                     => optional($this->confirmed_at)->toIso8601String(),
            'notified_at'                      => optional($this->notified_at)->toIso8601String(),
            'operated_at'                      => optional($this->operated_at)->toIso8601String(),
            'provider'                         => User::make($this->whenLoaded('from')),
            'provider_device_name'             => $this->from_device_name,
            'provider_channel_account_hash_id' => $this->from_channel_account_hash_id,
            'provider_channel_account_id'      => $this->from_channel_account_id,
            'provider_account'                 => data_get($this->fromChannelAccount, 'account'),
            'provider_account_name'            => data_get($this->fromChannelAccount,
                UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME,
                data_get($this->fromChannelAccount, UserChannelAccount::DETAIL_KEY_RECEIVER_NAME)),
            'provider_account_vendor_name'     => $this->getProviderAccountVendorName(),
            'provider_account_note'            => data_get($this->fromChannelAccount, 'note'),
            'provider_bank_card_branch'        => data_get($this->fromChannelAccount, 'bank_card_branch'),
            'qr_code_file_path'                => $this->qrCodeFilePath(data_get(
                $this->fromChannelAccount,
                UserChannelAccount::DETAIL_KEY_QR_CODE_FILE_PATH
            )),
            'actual_amount'                    => AmountDisplayTransformer::transform($this->actual_amount),
            'floating_amount'                  => AmountDisplayTransformer::transform($this->floating_amount),
            'fee'                              => $bcMath->max($merchantTransactionFee->actual_fee, $merchantTransactionFee->actual_profit),
            'merchant_fees'                    => TransactionFeeCollection::make($this->whenLoaded('transactionFees',
                function () {
                    return $this->transactionFees
                        ->filter($this->filteredByRole(\App\Model\User::ROLE_MERCHANT));
                })),
            'provider_fees'                    => TransactionFeeCollection::make($this->whenLoaded('transactionFees',
                function () {
                    return $this->transactionFees
                        ->filter($this->filteredByRole(\App\Model\User::ROLE_PROVIDER));
                })),
            'system_profit'                    => $this->whenLoaded('transactionFees', function () {
                return optional(
                        $this->transactionFees
                            ->whereNull('thirdchannel_id')
                            ->filter($this->filteredByRole(\App\Model\User::ROLE_ADMIN))
                            ->first()
                    )->profit ?? 0;
            }),
            'notify_url'                       => $this->notify_url,
            'client_ip'                        => $this->client_ipv4,
            'note'                             => $this->whenLoaded('channel', function () {
                return ($this->channel->note_enable && $this->note) ? $this->note : null;
            }),
            'currency' => $this->currency,
            'lockable'                         => $this->getLockable(),
            'unlockable'                       => $this->getUnlockable(),
            'confirmable'                      => $this->getConfirmable(),
            'failable'                         => $this->getChildTransactionConfirmable(),
            'locked'                           => $this->locked,
            'locked_at'                        => optional($this->locked_at)->toIso8601String(),
            'locked_by'                        => $this->lockedBy ? [
                'id'   => $this->lockedBy->getKey(),
                'name' => $this->lockedBy->name,
            ] : null,
            'refunded_at'                      => optional($this->refunded_at)->toIso8601String(),
            'refunded_by'                      => $this->refundedBy ? [
                'id'   => $this->refundedBy->getKey(),
                'name' => $this->refundedBy->name,
            ] : null,
            'should_refund_at'                 => optional($this->should_refund_at)->toIso8601String(),
            'thirdchannel'                     => $this->thirdChannel,
            'certificate_file_path'            => $this->temporaryUrl($this->certificate_file_path),
            'certificate_files'                => TransactionCertificateFileCollection::make($this->getCertificateFiles()),

            'real_name'                        => $this->when(
                isset($toChannel[UserChannelAccount::DETAIL_KEY_REAL_NAME]),
                data_get($toChannel, UserChannelAccount::DETAIL_KEY_REAL_NAME)
            ),
            'mobile_number'                      => $this->when(
                isset($toChannel['mobile_number']),
                data_get($toChannel, 'mobile_number')
            ),
            'red_envelope_password'            => $this->when(
                isset($toChannel['red_envelope_password']),
                data_get($toChannel, 'red_envelope_password')
            ),
            're_qq_qrcode_path'                => $this->when(
                isset($toChannel['re_qq_qrcode_path']),
                $this->temporaryQRcodeUrl(data_get($toChannel, 're_qq_qrcode_path'))
            ),
            'recorded_at'                      => $this->when(
                isset($toChannel['recorded_at']),
                data_get($toChannel, 'recorded_at') ? Carbon::parse(data_get($toChannel, 'recorded_at'))->toIso8601String() : null
            ),
            'usdt_rate' => $this->usdt_rate,
            '_search1' => $this->_search1
        ];
    }

    private function getProviderAccountVendorName()
    {
        switch ($this->channel_code) {
            case \App\Model\Channel::CODE_QR_ALIPAY:
                return '支付宝';
            case \App\Model\Channel::CODE_QR_WECHATPAY:
                return '微信支付';
            case \App\Model\Channel::CODE_QR_YFB:
                return '易付宝';
            case \App\Model\Channel::CODE_RE_ALIPAY:
                return '口令红包';
            case \App\Model\Channel::CODE_RE_QQ:
                return '面对面红包';
            case \App\Model\Channel::CODE_USDT:
                return 'USDT';
            default:
                return data_get($this->from_channel_account, UserChannelAccount::DETAIL_KEY_BANK_NAME);;
        }
    }

    private function qrCodeFilePath($qrCodeFilePath)
    {
        if (empty($qrCodeFilePath)) {
            return null;
        }

        try {
            return Storage::disk('user-channel-accounts-qr-code')->temporaryUrl($qrCodeFilePath, now()->addHour());
        } catch (RuntimeException $e) {
            Log::debug($e);

            return null;
        }
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

    private function getConfirmable()
    {
        if (!$this->locked) {
            return true;
        }

        return (
            $this->locked
            && optional($this->lockedBy)->is(auth()->user()->realUser())
            && in_array($this->status, [
                \App\Model\Transaction::STATUS_PAYING, \App\Model\Transaction::STATUS_PAYING_TIMED_OUT,
                \App\Model\Transaction::STATUS_SUCCESS, \App\Model\Transaction::STATUS_MANUAL_SUCCESS,
                \App\Model\Transaction::STATUS_THIRD_PAYING,
            ])
            && $this->type === \App\Model\Transaction::TYPE_PAUFEN_TRANSACTION
        );
    }

    private function getChildTransactionConfirmable()
    {
        if (!$this->locked) {
            return true;
        }

        return (
            $this->locked
            && optional($this->lockedBy)->is(auth()->user()->realUser())
            && in_array($this->status, [
                \App\Model\Transaction::STATUS_PAYING, \App\Model\Transaction::STATUS_PAYING_TIMED_OUT,
            ])
            && $this->type === \App\Model\Transaction::TYPE_PAUFEN_TRANSACTION
        );
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

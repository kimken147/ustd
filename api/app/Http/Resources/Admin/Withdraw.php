<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\UserChannelAccount;
use App\Http\Resources\User;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Repository\FeatureToggleRepository;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Http\Resources\TransactionCertificateFileCollection;
use App\Models\TransactionCertificateFile;
use Illuminate\Support\Facades\Storage;

/**
 * @property boolean locked
 * @property Carbon created_at
 * @property User|null lockedBy
 */
class Withdraw extends JsonResource
{

    /**
     * @var mixed
     */
    private $excludeAllRelativeWithdraws;

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                     => $this->getKey(),
            'is_parent'              => $this->isParent(),
            'is_child'               => $this->isChild(),
            'separated'              => $this->separated(),
            'type'                   => $this->type,
            'sub_type'               => $this->sub_type,
            'user'                   => User::make($this->whenLoaded('from')),
            'system_order_number'    => $this->system_order_number,
            'order_number'           => $this->order_number,
            'amount'                 => AmountDisplayTransformer::transform($this->amount),
            'usdt'                   => bcdiv($this->amount,($this->usdt_rate > 0 ? $this->usdt_rate : 1),2),
            'status'                 => $this->status,
            'notify_status'          => $this->notify_status,
            'created_at'             => $this->created_at->toIso8601String(),
            'confirmed_at'           => optional($this->confirmed_at)->toIso8601String(),
            'notified_at'            => optional($this->notified_at)->toIso8601String(),
            'provider'               => User::make($this->whenLoaded('to')),
            'to_channel_account'     => $this->whenLoaded('toChannelAccount'), // 不使用 resource UserChannelAccount::make() 因為 resource 裡要加載太多 relation
            'to_channel_account_hash_id' => $this->whenLoaded('toChannelAccount', function () {
                return $this->toChannelAccount->name;
            }),
            'actual_amount'          => AmountDisplayTransformer::transform($this->actual_amount),
            'floating_amount'        => AmountDisplayTransformer::transform($this->floating_amount),
            'bank_card_holder_name'  => data_get($this->from_channel_account, 'bank_card_holder_name'),
            'bank_name'              => data_get($this->from_channel_account, 'bank_name'),
            'bank_province'          => data_get($this->from_channel_account, 'bank_province'),
            'bank_city'              => data_get($this->from_channel_account, 'bank_city'),
            'bank_card_number'       => data_get($this->from_channel_account, 'bank_card_number'),
            'merchant_fees'          => TransactionFeeCollection::make($this->whenLoaded('transactionFees',
                function () {
                    return $this->transactionFees
                        ->filter($this->filteredByRole(\App\Model\User::ROLE_MERCHANT));
                })),
            'provider_fees'          => TransactionFeeCollection::make($this->whenLoaded('transactionFees',
                function () {
                    return $this->transactionFees
                        ->filter($this->filteredByRole(\App\Model\User::ROLE_PROVIDER));
                })),
            'system_profit'          => $this->whenLoaded('transactionFees', function () {
                return optional(
                        $this->transactionFees
                            ->whereNull('thirdchannel_id')
                            ->filter($this->filteredByRole(\App\Model\User::ROLE_ADMIN))
                            ->first()
                    )->profit ?? 0;
            }),
            'parent'                 => Withdraw::make($this->whenLoaded('parent')),
            'children'               => WithdrawCollection::make($this->whenLoaded('children')),
            'siblings'               => WithdrawCollection::make($this->whenLoaded('siblings')),
            'all_relative_withdraws' => $this->getAllRelativeWithdraws(),
            'notify_url'             => $this->notify_url,
            'note'                   => $this->note,
            'currency' => $this->currency,
            'notes'                  => TransactionNoteCollection::make($this->whenLoaded('transactionNotes')),
            'note_exist'             => $this->note || ($this->relationLoaded('transactionNotes') && $this->transactionNotes->isNotEmpty()),
            'locked'                 => $this->locked,
            'locked_at'              => optional($this->locked_at)->toIso8601String(),
            'locked_by'              => $this->lockedBy ? [
                'id'   => $this->lockedBy->getKey(),
                'name' => $this->lockedBy->name,
            ] : null,
            'lockable'               => $this->getLockable(),
            'unlockable'             => $this->getUnlockable(),
            'confirmable'            => $this->getConfirmable(),
            'failable'               => $this->getFailable(),
            'paufenable'             => $this->getPaufenable(),
            'separatable'            => $this->getSeparatable(),
            'thirdchannel'           => $this->thirdChannel,

            'certificate_file_path'             => $this->temporaryUrl($this->certificate_file_path),
            'certificate_files'                 => TransactionCertificateFileCollection::make($this->getCertificateFiles()),
            '_search1' => $this->_search1
        ];
    }

    private function separated()
    {
        return $this->whenLoaded('children', function () {
            return (boolean) $this->children->count();
        });
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

    private function getAllRelativeWithdraws()
    {
        if ($this->excludeAllRelativeWithdraws) {
            return [];
        }

        $allRelativeWithdraws = collect();

        if ($this->resource->isRoot()) {
            $allRelativeWithdraws->add(Withdraw::make((clone $this->resource)->unsetRelations())->excludeAllRelativeWithdraws());

            $this->whenLoaded('children', function () use ($allRelativeWithdraws) {
                foreach ($this->children as $child) {
                    $allRelativeWithdraws->add(Withdraw::make((clone $child)->unsetRelations())->excludeAllRelativeWithdraws());
                }
            });
        } else {
            $allRelativeWithdraws->add(Withdraw::make((clone $this->parent)->unsetRelations())->excludeAllRelativeWithdraws());

            $this->whenLoaded('siblings', function () use ($allRelativeWithdraws) {
                foreach ($this->siblings as $sibling) {
                    $allRelativeWithdraws->add(Withdraw::make((clone $sibling)->unsetRelations())->excludeAllRelativeWithdraws());
                }
            });
        }

        return $allRelativeWithdraws;
    }

    public function excludeAllRelativeWithdraws()
    {
        $this->excludeAllRelativeWithdraws = true;

        return $this;
    }

    private function getLockable()
    {
        // 已拆單之主訂單禁止任何操作
        if ($this->separated()) {
            return false;
        }

        if ($this->type === Transaction::TYPE_NORMAL_WITHDRAW && $this->status !== Transaction::STATUS_PENDING_REVIEW) {
            return !$this->locked;
        }

        $featureToggleRepository = app(FeatureToggleRepository::class);
        if ($featureToggleRepository->enabled(\App\Model\FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            return true;
        }

        if (!$featureToggleRepository->enabled(\App\Model\FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT)) {
            return false;
        }

        $paufenWithdrawTimeoutInSeconds = $featureToggleRepository->valueOf(\App\Model\FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT, 0);

        if ($this->type === Transaction::TYPE_PAUFEN_WITHDRAW && $this->created_at->diffInSeconds(now()) >= $paufenWithdrawTimeoutInSeconds && $this->status === Transaction::STATUS_MATCHING) {
            return !$this->locked;
        }

        return false;
    }

    private function getUnlockable()
    {
        // 已拆單之主訂單禁止任何操作
        if ($this->separated()) {
            return false;
        }

        $featureToggleRepository = app(FeatureToggleRepository::class);
        if ($featureToggleRepository->enabled(\App\Model\FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            return true;
        }

        if ($this->type !== Transaction::TYPE_NORMAL_WITHDRAW) {
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
        // 已拆單之主訂單禁止任何操作
        if ($this->separated()) {
            return false;
        }

        $featureToggleRepository = app(FeatureToggleRepository::class);
        if ($featureToggleRepository->enabled(\App\Model\FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            return true;
        }

        return $this->locked && optional($this->lockedBy)->is(auth()->user()->realUser()) && in_array($this->status, [
                Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED, Transaction::STATUS_THIRD_PAYING
            ])
            && !($this->type === Transaction::TYPE_PAUFEN_WITHDRAW && $this->to_id); # 被码商抢单后不能成功
    }

    private function getFailable()
    {
        // 已拆單之主訂單禁止任何操作
        if ($this->separated()) {
            return false;
        }

        $featureToggleRepository = app(FeatureToggleRepository::class);
        if ($featureToggleRepository->enabled(\App\Model\FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            return true;
        }

        return $this->locked && optional($this->lockedBy)->is(auth()->user()->realUser()) && in_array($this->status, [
                Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED, Transaction::STATUS_THIRD_PAYING
            ])
            && !($this->type === Transaction::TYPE_PAUFEN_WITHDRAW && $this->to_id); # 被码商抢单后不能失败
    }

    private function getPaufenable()
    {
        // 已拆單之主訂單禁止任何操作
        if ($this->separated()) {
            return false;
        }

        $featureToggleRepository = app(FeatureToggleRepository::class);
        if ($featureToggleRepository->enabled(\App\Model\FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            return true;
        }

        return $this->locked && optional($this->lockedBy)->is(auth()->user()->realUser()) && in_array($this->status, [
                Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED
            ]) && ($this->type === Transaction::TYPE_NORMAL_WITHDRAW) && ($this->from->role === \App\Model\User::ROLE_MERCHANT);
    }

    private function getSeparatable()
    {
        return $this->locked && optional($this->lockedBy)->is(auth()->user()->realUser()) && in_array($this->status, [
                Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED
            ]) && $this->isParent() && !$this->separated() && ($this->from->role === \App\Model\User::ROLE_MERCHANT)
            && !($this->type === Transaction::TYPE_PAUFEN_WITHDRAW && $this->to_id); # 被码商抢单后不能拆单
    }

    private function temporaryUrl($certificateFilePath)
    {
        if ($certificateFilePath) {
            return Storage::disk('transaction-certificate-files')->temporaryUrl($certificateFilePath, now()->addHour());
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

        if (!$this->relationLoaded('certificateFiles')) {
            return [];
        }

        return $this->certificateFiles;
    }
}

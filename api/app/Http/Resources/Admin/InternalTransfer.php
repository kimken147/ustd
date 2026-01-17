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

class InternalTransfer extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'system_order_number'    => $this->system_order_number,
            'order_number'           => $this->order_number,
            'amount'                 => AmountDisplayTransformer::transform($this->amount),
            'status'                 => $this->status,
            'provider'               => User::make($this->whenLoaded('to')),
            'to_channel_account'     => $this->whenLoaded('toChannelAccount'), // 不使用 resource UserChannelAccount::make() 因為 resource 裡要加載太多 relation
            'actual_amount'          => AmountDisplayTransformer::transform($this->actual_amount),
            'floating_amount'        => AmountDisplayTransformer::transform($this->floating_amount),
            'bank_card_holder_name'  => data_get($this->from_channel_account, 'bank_card_holder_name'),
            'bank_name'              => data_get($this->from_channel_account, 'bank_name'),
            'bank_card_number'       => data_get($this->from_channel_account, 'bank_card_number'),
            'note'                   => $this->note,
            '_search1'               => $this->_search1,
            'created_at'             => $this->created_at->toIso8601String(),
            'confirmed_at'           => optional($this->confirmed_at)->toIso8601String(),
            'notes'                  => TransactionNoteCollection::make($this->whenLoaded('transactionNotes')),
            'locked'                 => $this->locked,
            'locked_at'              => optional($this->locked_at)->toIso8601String(),
            'locked_by'              => $this->lockedBy ? [
                'id'   => $this->lockedBy->getKey(),
                'name' => $this->lockedBy->name,
            ] : null,
            'lockable'               => $this->getLockable(),
            'unlockable'             => $this->getUnlockable(),
            'confirmable'            => $this->getConfirmable(),
        ];
    }

    private function getConfirmable()
    {
        $featureToggleRepository = app(FeatureToggleRepository::class);
        if ($featureToggleRepository->enabled(\App\Model\FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            return true;
        }

        return $this->locked && optional($this->lockedBy)->is(auth()->user()->realUser()) && in_array($this->status, [
                Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED, Transaction::STATUS_THIRD_PAYING
            ])
            && !($this->type === Transaction::TYPE_PAUFEN_WITHDRAW && $this->to_id); # 被码商抢单后不能成功
    }

    private function getLockable()
    {
        if ($this->type === Transaction::TYPE_NORMAL_WITHDRAW && $this->status !== Transaction::STATUS_PENDING_REVIEW) {
            return !$this->locked;
        }

        $featureToggleRepository = app(FeatureToggleRepository::class);
        if ($featureToggleRepository->enabled(\App\Model\FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            return true;
        }

        return false;
    }

    private function getUnlockable()
    {
        $featureToggleRepository = app(FeatureToggleRepository::class);
        if ($featureToggleRepository->enabled(\App\Model\FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            return true;
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

}

<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\TransactionCertificateFileCollection;
use App\Http\Resources\User;
use App\Http\Resources\UserChannelAccount;
use App\Model\Transaction;
use App\Model\TransactionCertificateFile;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class Deposit extends JsonResource
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
            'id'                                => $this->getKey(),
            'type'                              => $this->type,
            'provider'                          => User::make($this->whenLoaded('to')),
            'merchant'                          => User::make($this->whenLoaded('from')),
            'system_order_number'               => $this->system_order_number,
            'order_number'                      => $this->order_number,
            'amount'                            => AmountDisplayTransformer::transform($this->amount),
            'status'                            => $this->status,
            'certificate_file_path'             => $this->temporaryUrl($this->certificate_file_path),
            'certificate_files'                 => TransactionCertificateFileCollection::make($this->getCertificateFiles()),
            'matched_at'                        => $this->type === Transaction::TYPE_NORMAL_DEPOSIT ? $this->created_at->toIso8601String() : optional($this->matched_at)->toIso8601String(),
            'created_at'                        => $this->created_at->toIso8601String(),
            'from_channel_account'              => (object) $this->from_channel_account,
            'to_channel_account'                => $this->whenLoaded('toChannelAccount'), // 不使用 resource UserChannelAccount::make() 因為 resource 裡要加載太多 relation
            'to_channel_account_hash_id' => $this->whenLoaded('toChannelAccount', function () {
                return $this->toChannelAccount->name;
            }),
            'confirmed_at'                      => optional($this->confirmed_at)->toIso8601String(),
            'note'                              => $this->note,
            'notes'                             => TransactionNoteCollection::make($this->whenLoaded('transactionNotes')),
            'note_exist'                        => $this->note || ($this->relationLoaded('transactionNotes') && $this->transactionNotes->isNotEmpty()),
            'locked'                            => $this->locked,
            'locked_at'                         => optional($this->locked_at)->toIso8601String(),
            'locked_by'                         => $this->lockedBy ? [
                'id'   => $this->lockedBy->getKey(),
                'name' => $this->lockedBy->name,
            ] : null,
            'lockable'                          => $this->getLockable(),
            'unlockable'                        => $this->getUnlockable(),
            'confirmable'                       => $this->getConfirmable(),
            'failable'                          => $this->getFailable(),
            'cancelable'                        => $this->getCancelable(), // 系統出
            'provider_wallet_settled'           => $this->to_wallet_settled,
            'provider_wallet_should_settled_at' => optional($this->to_wallet_should_settled_at)->toIso8601String(),
        ];
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

    private function getLockable()
    {
        // 只有跑分充值（提現）、一般充值可以在這邊鎖定
        if (!in_array($this->type, [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT])) {
            return false;
        }

        return !$this->locked;
    }

    private function getUnlockable()
    {
        // 只有跑分充值（提現）、一般充值可以在這邊解鎖
        if (!in_array($this->type, [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT])) {
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
        if (!in_array($this->type, [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT])) {
            return false;
        }

        return $this->locked && optional($this->lockedBy)->is(auth()->user()->realUser()) && in_array($this->status, [
                Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED
            ]);
    }

    private function getFailable()
    {
        if (!in_array($this->type, [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT])) {
            return false;
        }

        return $this->locked && optional($this->lockedBy)->is(auth()->user()->realUser()) && in_array($this->status, [
                Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED
            ]);
    }

    private function getCancelable()
    {
        if ($this->type !== Transaction::TYPE_PAUFEN_WITHDRAW) {
            return false;
        }

        if (!$this->locked) {
            return false;
        }

        if (!$this->lockedBy->is(auth()->user()->realUser())) {
            return false;
        }

        if (!in_array($this->status, [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED])) {
            return false;
        }

        return true;
    }
}

<?php

namespace App\Http\Resources\Provider;

use App\Http\Resources\TransactionCertificateFileCollection;
use App\Model\Transaction;
use App\Model\TransactionCertificateFile;
use App\Utils\AmountDisplayTransformer;
use App\Http\Resources\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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
            'id'                    => $this->getKey(),
            'order_number'          => $this->order_number,
            'system_order_number'   => $this->system_order_number,
            'amount'                => AmountDisplayTransformer::transform($this->amount),
            'status'                => $this->getStatus(),
            'provider'              => User::make($this->whenLoaded('to')),
            'certificate_file_path' => $this->temporaryUrl($this->certificate_file_path),
            'certificate_files'     => TransactionCertificateFileCollection::make($this->getCertificateFiles()),
            'from_channel_account'  => (object) $this->from_channel_account,
            'to_channel_account'    => (object) $this->to_channel_account,
            'created_at'            => $this->type === Transaction::TYPE_NORMAL_DEPOSIT ? $this->created_at->toIso8601String() : $this->matched_at->toIso8601String(),
            'confirmed_at'          => $this->getConfirmedAt(),
            'note'                  => $this->note,
            'notes'                 => TransactionNoteCollection::make($this->whenLoaded('transactionNotes')),
            'note_exist'            => $this->note || ($this->relationLoaded('transactionNotes') && $this->transactionNotes->isNotEmpty()),
        ];
    }

    private function getStatus()
    {
        if (in_array($this->status, [
                Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS
            ]) && !$this->to_wallet_settled) {
            return Transaction::STATUS_PAYING;
        }

        if (in_array($this->status, [Transaction::STATUS_RECEIVED])) {
            return Transaction::STATUS_PAYING;
        }

        return $this->status;
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

    private function getConfirmedAt()
    {
        if (!in_array($this->getStatus(),
            [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])) {
            return null;
        }

        return optional($this->confirmed_at)->toIso8601String();
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TransactionCertificateFile extends JsonResource
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
            'id'         => $this->getKey(),
            'path'       => $this->path,
            'url'        => $this->temporaryUrl($this->path),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
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

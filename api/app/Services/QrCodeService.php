<?php

namespace App\Services;

use App\Models\User;
use App\Utils\GuzzleHttpClientTrait;
use Endroid\QrCode\Builder\Builder as QrBuilder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Exception;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Zxing\QrReader;

class QrCodeService
{
    use GuzzleHttpClientTrait;

    public function decodeQrCode(UploadedFile $file): string
    {
        try {
            $qrCodeText = '';

            try {
                $qrCodeText = trim((new QrReader($file))->text());
            } catch (Exception $exception) {
                return abort(Response::HTTP_BAD_REQUEST, __('common.Invalid qr-code'));
            }

            if (!empty($qrCodeText)) {
                return $qrCodeText;
            }

            $response = $this->makeClient()
                ->post('http://api.qrserver.com/v1/read-qr-code/', [
                    RequestOptions::MULTIPART => [
                        [
                            'name'     => 'file',
                            'contents' => fopen($file->path(), 'r'),
                        ],
                        [
                            'name'     => 'MAX_FILE_SIZE',
                            'contents' => $file->getSize(),
                        ],
                    ],
                ]);

            $responseData = json_decode($response->getBody()->getContents());

            $qrCodeText = trim(data_get($responseData, '0.symbol.0.data'));

            abort_if(
                empty($qrCodeText),
                Response::HTTP_BAD_REQUEST,
                __('common.Invalid qr-code')
            );

            return $qrCodeText;
        } catch (TransferException $transferException) {
            return abort(Response::HTTP_BAD_REQUEST, __('common.Invalid qr-code'));
        }
    }

    public function getQrCodeFileBasePath(User $user): string
    {
        $userId = $user->getKey();

        return "users/$userId";
    }

    public function saveProcessedQrCode(string $data, User $user): string
    {
        $qrcode = QrBuilder::create()
            ->writer(new PngWriter())
            ->data($data)
            ->encoding(new Encoding('UTF-8'))
            ->margin(10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->size(500)
            ->build();

        $basePath = $this->getQrCodeFileBasePath($user);
        $fileHashName = Str::random(40);

        Storage::disk('user-channel-accounts-qr-code')->put(
            $path = trim("$basePath/$fileHashName", '/') . '.png',
            $qrcode->getString()
        );

        return $path;
    }
}

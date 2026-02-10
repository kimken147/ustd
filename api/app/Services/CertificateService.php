<?php

namespace App\Services;

use App\Models\Transaction;
use AWS;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateService
{
    public function createPresignedUrl()
    {
        $userId = auth()->user()->getAuthIdentifier();

        return Redis::funnel("funnel-create-certificate-presigned-url-$userId")->limit(1)->then(function () use ($userId) {
            $path = Str::random(40);
            $retryCount = 0;

            while (Storage::disk('transaction-certificate-files')->has($path) && $retryCount <= 10) {
                $path = Str::random(40);
                $retryCount += 1;
            }

            abort_if(
                Storage::disk('transaction-certificate-files')->has($path),
                Response::HTTP_BAD_REQUEST,
                '系统繁忙，请重试'
            );

            // 第二版電子回單使用
            $paths = collect(Cache::pull("certificate-paths-$userId") ?? []);

            $paths->push($path);

            Cache::put("certificate-paths-$userId", $paths, now()->addHour());

            // 暫時維持原樣以確保新舊程式重疊時不會有問題
            Cache::put("certificate-path-owner-$path", auth()->user()->getKey(), now()->addHour());

            $s3 = AWS::createClient('s3');

            $cmd = $s3->getCommand('PutObject', [
                'Bucket' => config('filesystems.disks.transaction-certificate-files.bucket'),
                'Key'    => $path,
            ]);

            $uri = (string) $s3->createPresignedRequest($cmd, '+1 hour')->getUri();

            return response()->json([
                'certificate_file_path'     => $path,
                'certificate_presigned_url' => $uri,
            ]);
        }, function () {
            abort(Response::HTTP_BAD_REQUEST, '请稍候重试');
        });
    }

    public function updateCertificate(Request $request, Transaction $transaction): Transaction
    {
        // 第一版
        if ($path = $request->input('certificate_file_path')) {
            abort_if(
                auth()->user()->getKey() != Cache::pull("certificate-path-owner-$path"),
                Response::HTTP_BAD_REQUEST,
                '档案名称错误'
            );

            $transaction->update(['certificate_file_path' => $path]);
        }

        $requestedPaths = collect($request->input('certificate_file_paths', []))->unique();

        // 第二版
        if ($requestedPaths->isNotEmpty()) {
            $userId = auth()->user()->getAuthIdentifier();
            $existingPaths = $transaction->certificateFiles->pluck('path');
            $paths = collect(Cache::pull("certificate-paths-$userId") ?? [])->merge($existingPaths);

            abort_if(
                $requestedPaths->intersect($paths)->count() !== $requestedPaths->count(),
                Response::HTTP_BAD_REQUEST,
                '档案名称错误'
            );

            DB::transaction(function () use ($transaction, $requestedPaths) {
                if ($transaction->certificate_file_path) {
                    $transaction->update(['certificate_file_path' => null]);
                }

                $transaction->certificateFiles()->delete();
                $transaction->certificateFiles()->createMany($requestedPaths->map(function ($path) {
                    return compact('path');
                }));
            });

            $transaction->load('certificateFiles');
        }

        return $transaction;
    }
}

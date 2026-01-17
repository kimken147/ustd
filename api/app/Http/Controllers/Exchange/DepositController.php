<?php

namespace App\Http\Controllers\Exchange;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use AWS;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DepositController extends Controller
{

    public function certificatesPresignedUrl(Transaction $deposit)
    {
        abort_if(
            !in_array($deposit->status, [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]),
            Response::HTTP_BAD_REQUEST,
            '目前状态无法修改电子回单'
        );

        $userId = auth()->user()->getAuthIdentifier();

        return Redis::funnel("funnel-create-certificate-presigned-url-$userId")->limit(1)->then(function () use ($userId
        ) {
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

    public function update(Request $request, Transaction $deposit)
    {
        abort_if(!optional($deposit->to)->is(auth()->user()), Response::HTTP_NOT_FOUND);

        $this->validate($request, [
            'certificate' => 'nullable|file',
            'note'        => 'nullable|string|max:50',
        ]);

        abort_if(
            $request->note
            && !in_array($deposit->status, [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]),
            Response::HTTP_BAD_REQUEST,
            '目前状态无法修改备注'
        );

        abort_if(
            $request->certificate
            && !in_array($deposit->status, [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]),
            Response::HTTP_BAD_REQUEST,
            '目前状态无法修改电子回单'
        );

        DB::transaction(function () use ($request, $deposit) {
            $this->updateNoteIfPresent($request, $deposit);

            $this->updateCertificateIfPresent($request, $deposit);
        });

        return response()->json(null);
    }

    private function updateNoteIfPresent(Request $request, Transaction $deposit)
    {
        if (!$request->note) {
            return $deposit;
        }

        abort_if(
            !$deposit->update([
                'note' => $request->note,
            ]),
            Response::HTTP_INTERNAL_SERVER_ERROR);

        return $deposit;
    }

    private function updateCertificateIfPresent(Request $request, Transaction $deposit)
    {
        // 第一版
        if ($path = $request->input('certificate_file_path')) {
            abort_if(
                auth()->user()->getKey() != Cache::pull("certificate-path-owner-$path"),
                Response::HTTP_BAD_REQUEST,
                '档案名称错误'
            );

            $deposit->update(['certificate_file_path' => $path]);
        }

        $requestedPaths = collect($request->input('certificate_file_paths', []));

        // 第二版
        if ($requestedPaths->isNotEmpty()) {
            $userId = auth()->user()->getAuthIdentifier();
            $existingPaths = $deposit->certificateFiles->pluck('path');
            $paths = collect(Cache::pull("certificate-paths-$userId") ?? [])->merge($existingPaths);

            abort_if(
                $requestedPaths->intersect($paths)->count() !== $requestedPaths->count(),
                Response::HTTP_BAD_REQUEST,
                '档案名称错误'
            );

            DB::transaction(function () use ($deposit, $requestedPaths) {
                if ($deposit->certificate_file_path) {
                    $deposit->update(['certificate_file_path' => null]);
                }

                $deposit->certificateFiles()->delete();
                $deposit->certificateFiles()->createMany($requestedPaths->map(function ($path) {
                    return compact('path');
                }));
            });

            $deposit->load('certificateFiles');
        }

        return $deposit;
    }
}

<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionCertificateFile;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DepositCertificateFileController extends Controller
{
    public function store(Transaction $deposit, Request $request)
    {
        $this->validate($request, [
            'path' => 'required|string|max:255',
        ]);

        /** @see \App\Http\Controllers\Provider\DepositController::certificatesPresignedUrl */
        abort_if(
            auth()->user()->getKey() != Cache::pull("certificate-path-owner-{$request->input('path')}"),
            Response::HTTP_BAD_REQUEST,
            '档案名称错误'
        );

        abort_if(
            !in_array($deposit->type, [Transaction::TYPE_NORMAL_DEPOSIT, Transaction::TYPE_PAUFEN_WITHDRAW]),
            Response::HTTP_BAD_REQUEST,
            '非充值订单无法上传电子回单'
        );

        abort_if(
            $deposit->certificateFiles()->count() === 2,
            Response::HTTP_BAD_REQUEST,
            '最多只能上传两张电子回单'
        );

        $certificateFile = DB::transaction(function () use ($deposit, $request) {
            if ($deposit->certificate_file_path) {
                $deposit->certificateFiles()->create(['path' => $deposit->certificate_file_path]);
                $deposit->update(['certificate_file_path' => null]);
            }

            return $deposit->certificateFiles()->create(['path' => $request->input('path')]);
        });

        return TransactionCertificateFile::make($certificateFile);
    }

    public function update(Transaction $deposit, $certificateFileId, Request $request)
    {
        $this->validate($request, [
            'path' => 'required|string|max:255',
        ]);

        /** @see \App\Http\Controllers\Provider\DepositController::certificatesPresignedUrl */
        abort_if(
            auth()->user()->getKey() != Cache::pull("certificate-path-owner-{$request->input('path')}"),
            Response::HTTP_BAD_REQUEST,
            '档案名称错误'
        );

        abort_if(
            !in_array($deposit->type, [Transaction::TYPE_NORMAL_DEPOSIT, Transaction::TYPE_PAUFEN_WITHDRAW]),
            Response::HTTP_BAD_REQUEST,
            '非充值订单无法上传电子回单'
        );

        // 舊版資料透過新界面更新，主動將資料移至新表並刪除舊資料
        if ($certificateFileId == 0) {
            $certificateFile = DB::transaction(function () use ($deposit, $request) {
                $certificateFile = $deposit->certificateFiles()->create(['path' => $request->input('path')]);
                $deposit->update(['certificate_file_path' => null]);

                return $certificateFile;
            });

            return TransactionCertificateFile::make($certificateFile);
        }

        $certificateFile = \App\Model\TransactionCertificateFile::findOrFail($certificateFileId);

        abort_if(
            $deposit->getKey() !== $certificateFile->transaction_id,
            Response::HTTP_NOT_FOUND
        );

        $certificateFile->update(['path' => $request->input('path')]);

        return TransactionCertificateFile::make($certificateFile);
    }
}

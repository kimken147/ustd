<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymayaResponseError;
use App\Jobs\MayaChangeEmail;
use App\Jobs\MayaChangePassword;
use App\Model\Transaction;
use App\Model\TransactionNote;
use App\Model\UserChannelAccount;
use App\Services\Maya\HelperService;
use App\Services\Maya\MayaLoginService;
use App\Services\Maya\PayMayaApiService;
use App\Services\Maya\LogService;
use App\Services\Maya\MayaService;
use App\Services\TransactionNoteService;
use App\Services\Transaction\TransactionService;
use App\Utils\TransactionUtil;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MayaController extends Controller
{
    private MayaLoginService $loginService;
    private TransactionService $transactionService;
    private HelperService $helperService;
    private TransactionUtil $transactionUtil;
    private TransactionNoteService $transactionNoteService;
    private MayaService $mayaService;

    public function __construct(
        MayaLoginService $loginService,
        PayMayaApiService $mayaApiService,
        TransactionService $transactionService,
        HelperService $helperService,
        TransactionUtil $transactionUtil,
        TransactionNoteService $transactionNoteService,
        MayaService $mayaService
    ) {
        $this->loginService = $loginService;
        $this->transactionService = $transactionService;
        $this->helperService = $helperService;
        $this->transactionUtil = $transactionUtil;
        $this->transactionNoteService = $transactionNoteService;
        $this->mayaService = $mayaService;
    }

    public function login(Request $request)
    {
        $orderNumber = $request->input("orderNumber");

        $transaction = $this->transactionService->findOneByOrderId(
            $orderNumber
        );
        if (!$transaction) {
            return response()->json(
                [
                    "message" => __('common.Transaction not found'),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }
        $phone = $request->input("phone") ?? "";
        $password = $request->input("password") ?? "";

        try {
            $this->transactionNoteService->create(
                $transaction->id,
                "Enter mobile number and passwowrd."
            );
            $data = $this->loginService->apiLogin($phone, $password);
            return response()->json($data);
        } catch (Exception $e) {
            return $this->processException($transaction, $e);
        }
    }

    public function otpLogin(Request $request)
    {
        $orderNumber = $request->input("orderNumber");
        $expiresChallengeId = $request->input("expiresChallengeId");
        $accessToken = $request->input("accessToken");
        $otp = $request->input("otp") ?? "";

        $transaction = $this->transactionService->findOneByOrderId(
            $orderNumber
        );
        if (!$transaction) {
            return response()->json(
                [
                    "message" => __('common.Transaction not found'),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $this->transactionNoteService->create(
                $transaction->id,
                "Enter OTP."
            );
            $profile = $this->loginService->apiLoginNext(
                $expiresChallengeId,
                $otp,
                $accessToken
            );
        } catch (\Exception $e) {
            return $this->processException($transaction, $e);
        }

        return response()->json([
            "appToken" => $profile["token"],
            "profile" => $profile["profile"],
        ]);
    }

    public function transferToMaya(Request $request)
    {
        $orderNumber = $request->input("orderNumber");
        $accessToken = $request->input("accessToken");
        $appToken = $request->input("appToken");
        $profile = $request->input("profile") ?? null;

        $transaction = $this->transactionService->findOneByOrderId(
            $orderNumber
        );
        $this->transactionNoteService->create(
            $transaction->id,
            "Confirm payment detail."
        );
        if ($transaction->status != Transaction::STATUS_PAYING) {
            return response()->json(
                [
                    "message" => __('common.Transaction cannot be paid'),
                ],
                Response::HTTP_BAD_REQUEST
            );
        }
        $phone = $transaction->from_channel_account["account"];
        $amount = $transaction->amount;
        $recipient = $this->helperService->normalizePhoneNumber($phone);

        $logService = new LogService(__CLASS__, __FUNCTION__);
        $logService->writeRequestLog(
            [],
            ["recipient" => $recipient, "amount" => $amount]
        );

        try {
            $transferId = $this->loginService->createP2pTransfer(
                $recipient,
                $amount,
                $accessToken,
                $appToken,
                $profile
                    ? $profile["firstName"] . " " . $profile["lastName"]
                    : null
            );
            $result = $this->loginService->executeP2pTransfer(
                $transferId,
                $accessToken,
                $appToken
            );
        } catch (\Exception $e) {
            return $this->processException($transaction, $e);
        }

        try {
            $this->transactionUtil->markAsSuccess(
                $transaction,
                null,
                true,
                false,
                false
            );
            $refNo = $result["request_reference_no"];
            $transaction->refresh();
            $transaction->_search1 = $refNo;
            $notes[] = "Ref No. " . $refNo;
            $transaction->save();

            foreach ($notes as $note) {
                TransactionNote::create([
                    "transaction_id" => $transaction->id,
                    "user_id" => 0,
                    "note" => $note,
                ]);
            }
        } catch (\Throwable $th) {
            var_dump($th);
        }
        return response()->noContent(Response::HTTP_CREATED);
    }

    public function changePassword(Request $request)
    {
        $ids = $request->input("ids") ?? [];
        $newPassword = $request->input("newPassword");

        $accounts = UserChannelAccount::whereIn("id", $ids)->get();
        foreach ($accounts as $account) {
            MayaChangePassword::dispatch($account, "init", [
                "newPassword" => $newPassword,
            ]);
        }
    }

    public function changeEmail(Request $request)
    {
        $ids = $request->input("ids") ?? [];
        $newEmail = $request->input("newEmail");

        $accounts = UserChannelAccount::whereIn("id", $ids)->get();
        foreach ($accounts as $account) {
            MayaChangeEmail::dispatch($account, "init", [
                "newEmail" => $newEmail,
            ]);
        }
    }

    private function processException(
        Transaction $transaction,
        PaymayaResponseError $e
    ) {
        $message = $this->mayaService->getMayaServiceExceptionMessage($e);
        $this->transactionNoteService->create($transaction->id, $message);

        return response()->json(
            [
                "message" => $message,
            ],
            Response::HTTP_BAD_REQUEST
        );
    }
}

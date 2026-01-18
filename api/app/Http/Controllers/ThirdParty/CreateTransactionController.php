<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThirdParty\Transaction;
use App\Services\Transaction\CreateTransactionService;
use App\Services\Transaction\DTO\CreateTransactionContext;
use App\Services\Transaction\DTO\CreateTransactionResult;
use App\Services\Transaction\Exceptions\TransactionValidationException;
use App\Utils\ThirdPartyResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CreateTransactionController extends Controller
{
    public function __construct(
        private CreateTransactionService $service
    ) {
        $this->middleware('parse.textplain.json')->only(['callback']);
    }

    public function __invoke(Request $request): JsonResponse
    {
        foreach (['channel_code', 'username', 'amount', 'notify_url', 'client_ip', 'sign'] as $requiredAttribute) {
            if (!$request->filled($requiredAttribute)) {
                return response()->json([
                    'http_status_code' => Response::HTTP_BAD_REQUEST,
                    'error_code' => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                    'message' => __('common.Missing parameter: :attribute', ['attribute' => $requiredAttribute]),
                ]);
            }
        }

        try {
            $context = CreateTransactionContext::fromThirdPartyRequest($request);
            $result = $this->service->create($context);

            return $this->jsonResponse($result);
        } catch (TransactionValidationException $e) {
            return $e->toThirdPartyResponse();
        }
    }

    private function jsonResponse(CreateTransactionResult $result): JsonResponse
    {
        $transaction = $result->transaction;
        $noteEnable = $transaction->channel->note_enable;

        $matchedInfo = [
            'casher_url' => $result->cashierUrl ?? urldecode(route('api.v1.cashier', $transaction->system_order_number)),
            'receiver_account' => $result->matchedInfo?->receiverAccount ?? '',
            'receiver_name' => $result->matchedInfo?->receiverName ?? '',
            'receiver_bank_name' => $result->matchedInfo?->receiverBankName ?? '',
            'receiver_bank_branch' => $result->matchedInfo?->receiverBankBranch ?? '',
            'note' => $noteEnable ? ($result->matchedInfo?->note ?? $transaction->note ?? '') : '',
        ];

        return Transaction::make($transaction)
            ->withMatchedInformation($matchedInfo)
            ->additional([
                'http_status_code' => Response::HTTP_CREATED,
                'message' => __('common.Match successful'),
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function callback(string $orderNumber, Request $request): JsonResponse|string
    {
        $result = $this->service->handleCallback($orderNumber, $request);

        if ($result->success) {
            return $result->responseBody ?? 'SUCCESS';
        }

        return response()->json(['message' => $result->error], $result->statusCode);
    }
}

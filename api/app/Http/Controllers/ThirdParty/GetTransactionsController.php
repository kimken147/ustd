<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThirdParty\TransactionListCollection;
use App\Model\Transaction;
use App\Model\User;
use App\Utils\ThirdPartyResponseUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use DateTimeInterface;

class GetTransactionsController extends Controller
{

    public function __invoke(Request $request, WhitelistedIpManager $whitelistedIpManager)
    {
        $requiredAttributes = [
            'username',
            'page',
            'started_at',
            'ended_at',
            'sign'
        ];

        foreach ($requiredAttributes as $requiredAttribute) {
            if (empty($request->$requiredAttribute)) {
                return response()->json([
                    'http_status_code' => Response::HTTP_BAD_REQUEST,
                    'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                    'message'          => __('common.Information is incorrect: :attribute', ['attribute' => $requiredAttribute]),
                ]);
            }
        }

        $this->validate($request, [
            'started_at' => ['nullable', 'date_format:' . DateTimeInterface::ATOM],  // 改為驗證整數
            'ended_at'   => ['nullable', 'date_format:' . DateTimeInterface::ATOM],
        ]);

        // 改成這樣
        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        // 檢查邏輯也稍微調整
        if ($startedAt && Carbon::now()->diffInMonths($startedAt) > 2) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_TRANSACTION_NOT_FOUND,
                'message'          => __('common.No data found')
            ]);
        }

        if (!$startedAt || $startedAt->diffInDays($endedAt) > 31) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
                'message'          => __('common.Time range cannot exceed one month')
            ]);
        }

        /** @var User|null $merchant */
        $merchant = User::where([
            ['username', $request->username],
            ['role', User::ROLE_MERCHANT]
        ])->first();

        if (!$merchant) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_USER_NOT_FOUND,
                'message'          => __('common.User not found'),
            ]);
        }

        $parameters = $request->except('sign');

        ksort($parameters);

        $sign = md5(urldecode(http_build_query($parameters) . '&secret_key=' . $merchant->secret_key));

        if (strcasecmp($sign, $request->sign)) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_INVALID_SIGN,
                'message'          => __('common.Signature error'),
            ]);
        }

        if ($whitelistedIpManager->isNotAllowedToUseThirdPartyApi($merchant, $request)) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_INVALID_IP,
                'message'          => __('common.Please contact admin to add IP to whitelist'),
            ]);
        }

        $transactionWhereList = [
            'to_id'        => $merchant->getKey(),
        ];
        $transactionTypes = [
            Transaction::TYPE_PAUFEN_TRANSACTION,
            Transaction::TYPE_NORMAL_WITHDRAW,
            Transaction::TYPE_PAUFEN_WITHDRAW
        ];
        $transactions = Transaction::where($transactionWhereList)
            ->whereIn('type', $transactionTypes)
            ->whereBetween('created_at', [$startedAt, $endedAt])
            ->orderBy('created_at', 'desc');

        if (!$transactions) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_TRANSACTION_NOT_FOUND,
                'message'          => __('common.Order not found'),
            ]);
        }

        $perPage = $request->input('per_page', 20);
        $transactionCollection = TransactionListCollection::make($transactions->paginate($perPage));
        return response()
            ->json($transactionCollection->toArray(request()));
    }
}

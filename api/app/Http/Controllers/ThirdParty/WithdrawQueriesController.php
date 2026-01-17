<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThirdParty\Withdraw;
use App\Model\Transaction;
use App\Model\User;
use App\Utils\ThirdPartyResponseUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WithdrawQueriesController extends Controller
{

    public function __invoke(Request $request, WhitelistedIpManager $whitelistedIpManager)
    {
        $requiredAttributes = [
            'username', 'order_number', 'sign'
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

        $sign = md5(urldecode(http_build_query($parameters).'&secret_key='.$merchant->secret_key));

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

        $withdraw = Transaction::whereIn('type', [
            Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW,
        ])
            ->where([
                'from_id'      => $merchant->getKey(),
                'order_number' => $request->input('order_number'),
            ])->first();

        if (!$withdraw) {
            return response()->json([
                'http_status_code' => Response::HTTP_BAD_REQUEST,
                'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_TRANSACTION_NOT_FOUND,
                'message'          => __('common.Order not found'),
            ]);
        }

        return Withdraw::make($withdraw)
            ->additional([
                'http_status_code' => 201,
                'message'          => __('common.Query successful'),
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}

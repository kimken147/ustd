<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThirdParty\Profile;
use App\Model\Transaction;
use App\Model\User;
use App\Utils\ThirdPartyResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProfileQueriesController extends Controller
{

    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $requiredAttributes = [
            'username', 'sign'
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
            ['username', $request->input('username')],
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

        return Profile::make($merchant)
            ->additional([
                'http_status_code' => 201,
                'message'          => __('common.Query successful'),
            ])
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PayMaya API URL List
    |--------------------------------------------------------------------------
    |
    */

    "client" => [
        "sessions" => [
            "url" => "client/v5/sessions",
            "method" => "post",
            "contentType" => "application/json",
        ],
        "apiChangeAccountPassword" => [
            "url" => "client/v1/accounts/password",
            "method" => "put",
            "contentType" => "application/json",
        ],
        "apiGetAccountBalance" => [
            "url" => "client/v1/accounts/balance",
            "method" => "get",
            "contentType" => "application/json",
        ],
        "apiChangeAccountEmail" => [
            "url" => "client/v1/accounts/profile",
            "method" => "put",
            "contentType" => "application/json",
        ],
        "apiGetAccountLimit" => [
            "url" => "client/v1/accounts/limit",
            "method" => "get",
            "contentType" => "application/json",
        ],
        "otpForInstapayVerify" => [
            "url" => "client/v1/mfa/challenges/instapay/:challengeId/verify",
            "method" => "post",
            "contentType" => "application/json",
        ],
        "otpForInstapayStart" => [
            "url" => "client/v1/mfa/challenges/instapay/:challengeId/start",
            "method" => "post",
            "contentType" => "application/json",
        ],
        "challengesLoginVerify" => [
            "url" => "client/v1/mfa/challenges/login/:challengeId/verify",
            "method" => "post",
            "contentType" => "application/json",
        ],
        "challengesLoginStart" => [
            "url" => "client/v1/mfa/challenges/login/:challengeId/start",
            "method" => "post",
            "contentType" => "application/json",
        ],
        "apiVerifyEmail" => [
            "url" => "client/v1/accounts/email/verify",
            "method" => "post",
            "contentType" => "application/json",
        ],
    ],
    "chd" => [
        "banks" => [
            "url" => "chd/v1/banks",
            "method" => "get",
        ],
        "createBankTransfer" => [
            "url" => "chd/v3/banktransfers",
            "method" => "post",
            "contentType" => "application/json",
        ],
        "createP2pTransfer" => [
            "url" => "chd/v3/p2p/transfers",
            "method" => "post",
            "contentType" => "application/json",
        ],
        "executeBankTransfer" => [
            "url" => "chd/v3/banktransfers/:transactionId/execute",
            "method" => "put",
            "contentType" => "application/json",
        ],
        "executeP2pTransfers" => [
            "url" => "chd/v3/p2p/transfers/:transactionId/execute",
            "method" => "put",
            "contentType" => "application/json",
        ],
    ],
    "auth" => [
        "accessToken" => [
            "url" => "auth/v3/accesstoken/noexpiry",
            "method" => "post",
            "contentType" => "application/x-www-form-urlencoded",
        ],
    ],
];

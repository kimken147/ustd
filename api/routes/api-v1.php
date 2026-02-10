<?php

use Illuminate\Support\Str;


Route::get("create-transactions", "CreateTransactionController")->name(
    "api.v1.create-transactions"
);
Route::get("cashier/{id}", "CashierController")->name("api.v1.cashier");
Route::post(
    "transactions/{transaction}/red-envelope-password",
    "Provider\TransactionController@updatePassword"
)->name("api.v1.update-red-envelope-password");
Route::post(
    "transactions/{transaction}/red-envelope-qrcode",
    "Provider\TransactionController@updateQRcode"
)->name("api.v1.update-red-envelope-qrcode");
Route::post(
    "transactions/{transaction}/bug-report",
    "Provider\TransactionController@bugReport"
)->name("api.v1.bug-report");
Route::get("transactions/{orderNumber}", "TransactionController@show");
Route::match(
    ["get", "post"],
    "callback/{order_number}",
    "ThirdParty\CreateTransactionController@callback"
);

Route::prefix("admin")
    ->name("admin.")
    ->namespace("Admin")
    ->group(function () {
        Route::post("pre-login", "AuthController@preLogin");
        Route::post("login", "AuthController@login");

        Route::middleware([
            "auth:api",
            "role:admin",
            "check.account.status",
            "check.whitelisted.ip",
            "check.account.token",
        ])->group(function () {
            Route::get("me", "AuthController@me");
            Route::post("change-password", "AuthController@changePassword");

            Route::apiResource("users", "UserController")->only([
                "index",
                "show",
            ]);
            Route::apiResource("user-channels", "UserChannelController")->only([
                "update",
            ]);
            Route::apiResource(
                "users.wallet-histories",
                "UserWalletHistoryController"
            )->only(["index", "update"]);

            Route::apiResource(
                "wallet-histories",
                "WalletHistoryController"
            )->only(["index"]);

            Route::apiResource("channels", "ChannelController")->only([
                "index",
                "update",
            ]);

            Route::apiResource("providers.devices", "DeviceController")->only([
                "index",
                "show",
            ]);
            Route::post(
                "providers/{provider}/password-resets",
                "ProviderController@resetPassword"
            );
            Route::post(
                "providers/{provider}/google2fa-secret-resets",
                "ProviderController@resetGoogle2faSecret"
            );
            Route::apiResource("providers", "ProviderController")->only([
                "index",
                "show",
                "store",
                "update",
                "destroy",
            ]);
            Route::apiResource(
                "provider-transaction-stats",
                "ProviderTransactionStatController"
            )->only(["index"]);
            Route::post(
                "providers/{provider}/control-downlines",
                "ProviderController@updateControlDownlines"
            );

            Route::post(
                "merchants/{merchant}/password-resets",
                "MerchantController@resetPassword"
            );
            Route::post(
                "merchants/{merchant}/secret-resets",
                "MerchantController@resetSecret"
            );
            Route::post(
                "merchants/{merchant}/google2fa-secret-resets",
                "MerchantController@resetGoogle2faSecret"
            );
            Route::apiResource("merchants", "MerchantController")->only([
                "index",
                "show",
                "store",
                "update",
                "destroy",
            ]);
            Route::get(
                "merchant-transaction-stats",
                "MerchantTransactionStatController@index"
            );

            Route::get("channels", "ChannelController@index");

            Route::put(
                "user-channel-accounts/sync",
                "UserChannelAccountController@sync"
            );
            Route::get(
                "user-channel-accounts/{userChannelAccount}/audits",
                "UserChannelAccountController@audits"
            );
            Route::post(
                "user-channel-accounts/massive-create",
                "UserChannelAccountController@massiveStore"
            );
            Route::apiResource(
                "user-channel-accounts",
                "UserChannelAccountController"
            )->only(["index", "update", "show", "store", "destroy"]);
            Route::post(
                "user-channel-accounts/batch-update",
                "UserChannelAccountController@batchUpdate"
            );

            Route::apiResource(
                "user-channel-account-stats",
                "UserChannelAccountStatController"
            )->only(["index"]);
            Route::apiResource(
                "online-user-channel-accounts",
                "OnlineUserChannelAccountController"
            )->only(["index"]);
            Route::apiResource(
                "online-ready-for-matching-users",
                "OnlineReadyForMatchingUserController"
            )->only(["index"]);
            Route::apiResource(
                "channel-amounts",
                "ChannelAmountController"
            )->only(["index"]);
            Route::post(
                "withdraw-account",
                "UserChannelAccountController@createWithdrawAccount"
            );

            Route::get(
                "transactions/statistics",
                "TransactionController@statistics"
            );
            Route::apiResource("transactions", "TransactionController")->only([
                "index",
                "store",
                "update",
                "show",
            ]);
            Route::post(
                "transactions/{transaction}/renotify",
                "TransactionController@renotify"
            );
            Route::post("transactions/demo", "TransactionController@demo");
            Route::apiResource(
                "transactions.child-transactions",
                "ChildTransactionController"
            )->only(["store"]);

            Route::apiResource("notification", "NotificationController")->only([
                "index",
                "update",
                "show",
            ]);

            Route::apiResource("deposits", "DepositController")->only([
                "index",
                "update",
                "show",
            ]);

            Route::apiResource(
                "user-bank-cards",
                "UserBankCardController"
            )->only(["index", "update", "destroy"]);

            Route::apiResource("withdraws", "WithdrawController")->only([
                "index",
                "update",
                "show",
            ]);
            Route::apiResource(
                "withdraws.child-withdraws",
                "ChildWithdrawController"
            )->only(["store"]);

            Route::apiResource(
                "internal-transfers",
                "InternalTransferController"
            )
                ->parameters(["internal-transfers" => "transfer"])
                ->only(["index", "store", "update"]);

            Route::apiResource(
                "feature-toggles",
                "FeatureToggleController"
            )->only(["index", "update"]);

            Route::post(
                "sub-accounts/{subAccount}/password-resets",
                "SubAccountController@resetPassword"
            );
            Route::post(
                "sub-accounts/{subAccount}/google2fa-secret-resets",
                "SubAccountController@resetGoogle2faSecret"
            );
            Route::apiResource("sub-accounts", "SubAccountController")->only([
                "index",
                "store",
                "show",
                "update",
            ]);

            Route::apiResource("permissions", "PermissionController")->only([
                "index",
            ]);

            Route::apiResource(
                "channel-groups",
                "ChannelGroupController"
            )->only(["index", "store", "update", "destroy"]);

            Route::apiResource(
                "system-bank-cards",
                "SystemBankCardController"
            )->only(["index", "show", "store", "update", "destroy"]);
            Route::post(
                "batch-update-system-bank-cards",
                "BatchUpdateSystemBankCardController"
            );

            Route::apiResource(
                "whitelisted-ips",
                "WhitelistedIpController"
            )->only(["index", "store", "update", "destroy"]);
            Route::post(
                "batch-update-whitelisted-ips",
                "WhitelistedIpController@batchUpdate"
            );

            Route::get("banned/ip", "BannedController@getBanIp");
            Route::post("banned/ip", "BannedController@banIp");
            Route::delete("banned/ip/{ip}", "BannedController@allowIp");
            Route::put("banned/ip/{id}", "BannedController@updateIpNote");
            Route::get("banned/realname", "BannedController@getBanRealname");
            Route::post("banned/realname", "BannedController@banRealname");
            Route::delete(
                "banned/realname/{realname}",
                "BannedController@allowRealname"
            );
            Route::put(
                "banned/realname/{id}",
                "BannedController@updateRealnameNote"
            );

            Route::apiResource(
                "time-limit-banks",
                "TimeLimitBankController"
            )->only(["index", "store", "update", "destroy"]);
            Route::post(
                "batch-destroy-time-limit-banks",
                "TimeLimitBankController@batchDestroy"
            );

            Route::apiResource(
                "matching-deposit-rewards",
                "MatchingDepositRewardController"
            )->only(["index", "store", "update", "destroy"]);
            Route::apiResource(
                "transaction-rewards",
                "TransactionRewardController"
            )->only(["index", "store", "update", "destroy"]);

            Route::get(
                "transactions/{transaction}/transaction-notes",
                "TransactionNoteController@index"
            );
            Route::apiResource(
                "transaction-notes",
                "TransactionNoteController"
            )->only(["store", "show"]);

            Route::get("bank-report", "BankController@exportCsv");
            Route::apiResource("banks", "BankController")->only([
                "index",
                "store",
                "update",
                "destroy",
            ]);

            Route::apiResource(
                "merchant-matching-deposit-groups",
                "MerchantMatchingDepositGroupController"
            )->only(["index", "store", "destroy"]);
            Route::post(
                "batch-update-merchant-matching-deposit-groups",
                "MerchantMatchingDepositGroupController@batchUpdate"
            );

            Route::apiResource(
                "merchant-transaction-groups",
                "MerchantTransactionGroupController"
            )->only(["index", "store", "destroy"]);
            Route::post(
                "batch-update-merchant-transaction-groups",
                "MerchantTransactionGroupController@batchUpdate"
            );

            Route::apiResource(
                "merchant-third-channel",
                "MerchantThirdChannelController"
            )->only(["index", "store", "destroy", "update"]);
            Route::post(
                "batch-update-merchant-third-channel",
                "MerchantThirdChannelController@batchUpdate"
            );

            Route::get("transaction-report", "TransactionController@exportCsv");
            Route::get("deposit-report", "DepositController@exportCsv");
            Route::get("withdraw-report", "WithdrawController@exportCsv");

            Route::apiResource("thirdchannel", "ThirdChannelController")->only([
                "index",
                "update",
            ]);
            Route::post("heartbeats", "HeartbeatController");

            Route::apiResource("notifications", "NotificationController")->only(
                ["index"]
            );

            Route::apiResource('tags', 'TagController');
        });

        Route::prefix("statistics")->group(function () {
            Route::post("", "StatisticsController@index");
            Route::post("date", "StatisticsController@date");
            Route::get("v1", "StatisticsController@v1");
        });
    });

Route::prefix("provider")
    ->name("provider.")
    ->namespace("Provider")
    ->group(function () {
        Route::post("pre-login", "AuthController@preLogin");
        Route::post("login", "AuthController@login");

        Route::post(
            "/vn/transaction-notifications",
            "TransactionNotificationVnController"
        );

        Route::middleware([
            "auth:api",
            "role:provider",
            "check.account.status",
            "check.whitelisted.ip",
        ])->group(function () {
            Route::get("me", "AuthController@me");
            Route::put("me", "AuthController@updateMe");
            Route::post("change-password", "AuthController@changePassword");

            Route::apiResource(
                "wallet-histories",
                "WalletHistoryController"
            )->only(["index"]);

            Route::get("channels", "ChannelController@index");

            Route::apiResource(
                "member-user-channels",
                "MemberUserChannelController"
            )->only(["update"]);

            Route::post(
                "members/{member}/password-resets",
                "MemberController@resetPassword"
            );
            Route::post(
                "members/{member}/google2fa-secret-resets",
                "MemberController@resetGoogle2faSecret"
            );
            Route::apiResource("members", "MemberController")->only([
                "index",
                "show",
                "store",
                "update",
            ]);
            Route::post(
                "{provider}/control-downlines",
                "UserController@updateControlDownlines"
            );

            Route::apiResource(
                "feature-toggles",
                "FeatureToggleController"
            )->only(["index"]);

            Route::apiResource(
                "user-channel-accounts",
                "UserChannelAccountController"
            )->only(["index", "store", "update", "show", "destroy"]);
            Route::post(
                "withdraw-account",
                "UserChannelAccountController@createWithdrawAccount"
            );

            Route::put(
                "update-user-channel-accounts-daily-limit/{id}",
                "UserChannelAccountController@dailyLimitUpdate"
            );
            Route::put(
                "update-user-channel-accounts-monthly-limit/{id}",
                "UserChannelAccountController@monthlyLimitUpdate"
            );
            Route::put(
                "update-user-channel-accounts-balance/{id}",
                "UserChannelAccountController@balanceUpdate"
            );
            Route::put(
                "update-user-channel-accounts-bank-id/{id}",
                "UserChannelAccountController@bankIdUpdate"
            );
            Route::put(
                "update-user-channel-accounts-bank-branch/{id}",
                "UserChannelAccountController@branchUpdate"
            );

            Route::apiResource("transactions", "TransactionController")->only([
                "index",
                "update",
                "show",
            ]);
            Route::put(
                "transactions/{transaction}/certificates-presigned-url",
                "TransactionController@certificatesPresignedUrl"
            );

            Route::apiResource("notification", "NotificationController")->only([
                "index",
                "update",
                "show",
            ]);

            Route::get(
                "transactions/{transaction}/transaction-notes",
                "TransactionNoteController@index"
            );
            Route::apiResource(
                "transaction-notes",
                "TransactionNoteController"
            )->only(["store", "show"]);

            Route::put(
                "deposits/{deposit}/certificates-presigned-url",
                "DepositController@certificatesPresignedUrl"
            );
            Route::apiResource("deposits", "DepositController")->only([
                "index",
                "store",
                "update",
                "show",
            ]);

            Route::apiResource(
                "matching-deposits",
                "MatchingDepositController"
            )->only(["index", "update"]);

            Route::apiResource("bank-cards", "BankCardController")->only([
                "index",
                "store",
                "update",
                "destroy",
            ]);

            Route::apiResource(
                "bank-card-numbers",
                "BankCardNumberController"
            )->only(["show"]);

            Route::apiResource("withdraws", "WithdrawController")->only([
                "index",
                "store",
                "show",
            ]);

            Route::put("devices", "DeviceController@batchUpdateAll");
            Route::apiResource("devices", "DeviceController")->only([
                "index",
                "show",
                "store",
                "update",
                "destroy",
            ]);

            Route::post("heartbeats", "HeartbeatController");

            Route::post(
                "transaction-notifications",
                "TransactionNotificationController"
            );

            Route::apiResource(
                "channel-groups",
                "ChannelGroupController"
            )->only(["index"]);

            Route::apiResource(
                "channel-amounts",
                "ChannelAmountController"
            )->only(["index"]);

            Route::apiResource(
                "transaction-stats",
                "TransactionStatController"
            )->only(["index"]);

            Route::get("users/{username}", "UserController@show");
            Route::post("balance-transfers", "BalanceTransferController@store");

            Route::apiResource("banks", "BankController")->only(["index"]);

            Route::get("descendants", "UserController@descendants");

            Route::get("generic-search", "GenericSearchController");
            Route::get("statistic-report", "StatisticReportController");
        });
    });

Route::prefix("merchant")
    ->name("merchant.")
    ->namespace("Merchant")
    ->group(function () {
        Route::post("pre-login", "AuthController@preLogin");
        Route::post("login", "AuthController@login");

        Route::middleware([
            "auth:api",
            "role:merchant",
            "check.account.status",
            "check.whitelisted.ip",
        ])->group(function () {
            Route::get("me", "AuthController@me");
            Route::post("change-password", "AuthController@changePassword");
            Route::get("secret-key", "SecretKeyController");

            Route::get(
                "wallet-histories-report",
                "WalletHistoryController@exportCsv"
            );
            Route::apiResource(
                "wallet-histories",
                "WalletHistoryController"
            )->only(["index"]);

            Route::get("channels", "ChannelController@index");

            Route::apiResource(
                "member-user-channels",
                "MemberUserChannelController"
            )->only(["update"]);

            Route::post("self", "UserController@update");
            Route::put(
                "google2fa_secret",
                "UserController@resetGoogle2faSecret"
            );

            Route::post(
                "members/{member}/password-resets",
                "MemberController@resetPassword"
            );
            Route::post(
                "members/{member}/google2fa-secret-resets",
                "MemberController@resetGoogle2faSecret"
            );
            Route::apiResource("members", "MemberController")->only([
                "index",
                "show",
                "store",
                "update",
            ]);

            Route::post(
                "sub-accounts/{subAccount}/password-resets",
                "SubAccountController@resetPassword"
            );
            Route::post(
                "sub-accounts/{subAccount}/google2fa-secret-resets",
                "SubAccountController@resetGoogle2faSecret"
            );
            Route::apiResource("sub-accounts", "SubAccountController")->only([
                "index",
                "store",
                "show",
                "update",
            ]);

            Route::get("transaction-report", "TransactionController@exportCsv");
            Route::apiResource("transactions", "TransactionController")->only([
                "index",
                "show",
            ]);

            Route::get(
                "transactions/{transaction}/transaction-notes",
                "TransactionNoteController@index"
            );

            Route::apiResource("bank-cards", "BankCardController")->only([
                "index",
                "store",
                "update",
                "destroy",
            ]);

            Route::apiResource(
                "bank-card-numbers",
                "BankCardNumberController"
            )->only(["show"]);

            Route::get("banks", "BankController@index");

            Route::get("withdraw-report", "WithdrawController@exportCsv");
            Route::apiResource("withdraws", "WithdrawController")->only([
                "index",
                "store",
                "update",
                "show",
            ]);
            Route::apiResource(
                "agency-withdraws",
                "AgencyWithdrawController"
            )->only(["store"]);

            Route::apiResource(
                "channel-groups",
                "ChannelGroupController"
            )->only(["index"]);

            Route::apiResource(
                "channel-amounts",
                "ChannelAmountController"
            )->only(["index"]);

            Route::get("transaction-stats", "TransactionStatController@index");
            Route::get("transaction-stats/v1", "TransactionStatController@v1");

            Route::get("descendants", "UserController@descendants");

            Route::apiResource("getUsdtRate", "UsdtRateController")->only([
                "index",
            ]);
        });
    });

Route::prefix("third-party")
    ->namespace("ThirdParty")
    ->group(function () {
        Route::post("create-transactions", "CreateTransactionController")->name(
            "api.v1.third-party.create-transactions"
        );
        Route::post("profile-queries", "ProfileQueriesController");
        Route::post("withdraw-queries", "WithdrawQueriesController");
        Route::post("transaction-queries", "TransactionQueriesController");
        Route::post("batch-transaction-queries", "GetTransactionsController");
        Route::apiResource("withdraws", "WithdrawController")->only(["store"]);
        Route::apiResource(
            "agency-withdraws",
            "AgencyWithdrawController"
        )->only(["store"]);
        Route::get("rate/{coin}/{currency}", "UsdtController@rate");
    });





Route::prefix("app")
    ->namespace("App")
    ->group(function () {
        Route::apiResource("error-logs", "ErrorLogController")->only(["store"]);
    });

$telegramToken = config("services.telegram-bot-api.token", function () {
    return Str::random(40);
});

Route::post("/telegram/$telegramToken/webhook", "TelegramWebhookController");



Route::prefix("vn")
    ->namespace("Country")
    ->group(function () {
        Route::get("direct/{order}", "VietnamController@getTransaction")->name(
            "api.v1.vn.get-tx"
        );
        Route::post(
            "direct/{order}",
            "VietnamController@updateTransaction"
        )->name("api.v1.vn.update-tx");
        Route::post("daifu/{order}", "VietnamController@updateDaifu")->name(
            "api.v1.vn.update-daifu"
        );
    });



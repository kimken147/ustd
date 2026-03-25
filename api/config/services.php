<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'telegram-bot-api' => [
        'token'                    => env('TELEGRAM_BOT_TOKEN'),
        'system-admin-group-id'    => env('TELEGRAM_BOT_SYSTEM_ADMIN_GROUP_ID'),
        'engineer-leader-group-id' => env('TELEGRAM_BOT_ENGINEER_LEADER_GROUP_ID'),
    ],

    'trongrid' => [
        'api_key'         => env('TRONGRID_API_KEY'),
        'base_url'        => env('TRONGRID_BASE_URL', 'https://api.trongrid.io'),
        'fee_limit'       => (int) env('TRONGRID_FEE_LIMIT', 100000000),
        'min_trx_balance' => env('TRONGRID_MIN_TRX_BALANCE', '30'),
        'usdt_contract'   => env('TRONGRID_USDT_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t'),
        'min_gas_transfer_amount' => env('TRONGRID_MIN_GAS_TRANSFER', '1'),
    ],

    'energy_rental' => [
        'provider'       => env('ENERGY_RENTAL_PROVIDER', ''),
        'netts' => [
            'api_key'        => env('NETTS_API_KEY', ''),
            'whitelisted_ip' => env('NETTS_WHITELISTED_IP', ''),
            'base_url'       => env('NETTS_BASE_URL', 'https://netts.io'),
        ],
    ],

    'ethereum' => [
        'rpc_url'         => env('ETHEREUM_RPC_URL', 'https://eth-mainnet.g.alchemy.com/v2'),
        'rpc_api_key'     => env('ETHEREUM_RPC_API_KEY', ''),
        'explorer_url'    => env('ETHEREUM_EXPLORER_URL', 'https://api.etherscan.io'),
        'explorer_api_key' => env('ETHEREUM_EXPLORER_API_KEY', ''),
        'usdt_contract'   => env('ETHEREUM_USDT_CONTRACT', '0xdAC17F958D2ee523a2206206994597C13D831ec7'),
        'token_decimals'  => (int) env('ETHEREUM_TOKEN_DECIMALS', 6),
        'chain_id'        => (int) env('ETHEREUM_CHAIN_ID', 1),
        'min_native_balance' => env('ETHEREUM_MIN_ETH_BALANCE', '0.005'),
        'max_gas_price'   => env('ETHEREUM_MAX_GAS_PRICE', '50000000000'),
        'gas_limit_token_transfer' => (int) env('ETHEREUM_GAS_LIMIT_TOKEN', 80000),
        'gas_limit_native_transfer' => (int) env('ETHEREUM_GAS_LIMIT_NATIVE', 21000),
        'min_gas_transfer_amount' => env('ETH_MIN_GAS_TRANSFER', '0.001'),
    ],

    'bsc' => [
        'rpc_url'         => env('BSC_RPC_URL', 'https://bnb-mainnet.g.alchemy.com/v2'),
        'rpc_api_key'     => env('BSC_RPC_API_KEY', ''),
        'explorer_url'    => env('BSC_EXPLORER_URL', 'https://api.bscscan.com'),
        'explorer_api_key' => env('BSC_EXPLORER_API_KEY', ''),
        'usdt_contract'   => env('BSC_USDT_CONTRACT', '0x55d398326f99059fF775485246999027B3197955'),
        'token_decimals'  => (int) env('BSC_TOKEN_DECIMALS', 18),
        'chain_id'        => (int) env('BSC_CHAIN_ID', 56),
        'min_native_balance' => env('BSC_MIN_BNB_BALANCE', '0.005'),
        'max_gas_price'   => env('BSC_MAX_GAS_PRICE', '10000000000'),
        'gas_limit_token_transfer' => (int) env('BSC_GAS_LIMIT_TOKEN', 80000),
        'gas_limit_native_transfer' => (int) env('BSC_GAS_LIMIT_NATIVE', 21000),
        'min_gas_transfer_amount' => env('BSC_MIN_GAS_TRANSFER', '0.001'),
    ],

];

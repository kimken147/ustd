<?php

use App\Models\FeatureToggle;
use Illuminate\Database\Seeder;

class FeatureToggleSeeder extends Seeder
{

    private $features = [
        FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE               => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '1000',
                "unit" => "元"
            ],
            "hidden" => false
        ],
        FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT          => [
            'enabled' => true,
            'input'   => [
                'type'  => 'text',
                'value' => '0',
                "unit" => "秒"
            ],
            "hidden" => true
        ],
        FeatureToggle::FEATURE_MIN_COUNT_REGULAR_CUSTOMER_ENABLED          => [
            'enabled' => true,
            "hidden" => true,
            'input'   => [
                'type'  => 'text',
                'value' => '10',

            ],
        ],
        FeatureToggle::FEATURE_PROCESS_TRANSACTION_NOTIFICATION            => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '10',
                "unit" => "秒"
            ],
            "hidden" => true
        ],
        FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE_IN_PERCENT    => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '70',
                "unit" => "%"
            ],
            "hidden" => false
        ],
        FeatureToggle::PROVIDER_TRANSFER_BALANCE                           => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '0',
                "unit" => "元"
            ],
            "hidden" => true
        ],
        FeatureToggle::INITIAL_PROVIDER_FROZEN_BALANCE                     => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '0',
                "unit" => "元"
            ],
            "hidden" => false
        ],
        FeatureToggle::NOTIFY_ADMIN_UPDATE_BALANCE                         => [
            'enabled' => true,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
            "hidden" => false
        ],
        FeatureToggle::NOTIFY_ADMIN_LOGIN                                  => [
            'enabled' => true,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0',
            ],
            "hidden" => false
        ],
        FeatureToggle::NOTIFY_ADMIN_RESET_PASSWORD                         => [
            'enabled' => true,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
            "hidden" => false
        ],
        FeatureToggle::NOTIFY_ADMIN_RESET_GOOGLE2FA_SECRET                 => [
            'enabled' => true,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
            "hidden" => false
        ],
        FeatureToggle::DISABLE_SHOWING_ACCOUNT_ON_QR_ALIPAY_MATCHED_PAGE   => [
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
            "hidden" => false
        ],
        FeatureToggle::MIN_PROVIDER_NORMAL_DEPOSIT_AMOUNT                  => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '1000',
                "unit" => "元"
            ],
            "hidden" => false
        ],
        FeatureToggle::DISABLE_PROVIDER_CREATE_NEW_MEMBER                  => [
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
            "hidden" => false
        ],
        FeatureToggle::TRANSACTION_CREATION_RATE_LIMIT                     => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '0',
                "unit" => "笔"
            ],
            "hidden" => false
        ],
        FeatureToggle::MAX_PROVIDER_NORMAL_DEPOSIT_AMOUNT                  => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '50000',
                "unit" => "元"
            ],
            "hidden" => false
        ],
        FeatureToggle::LATE_NIGHT_BANK_LIMIT                               => [
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
            "hidden" => true
        ],
        FeatureToggle::DISABLE_SHOWING_QR_CODE_ON_QR_ALIPAY_MATCHED_PAGE   => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '0'
            ],
            "hidden" => true
        ],
        FeatureToggle::ENABLE_AGENCY_WITHDRAW                              => [
            'enabled' => true,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
            "hidden" => false
        ],
        FeatureToggle::MATCHING_DEPOSIT_REWARD                             => [
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
            "hidden" => false
        ],
        FeatureToggle::DISABLE_SHOWING_QR_CODE_ON_ALIPAY_BANK_MATCHED_PAGE => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '0'
            ],
            "hidden" => true
        ],
        FeatureToggle::PROVIDER_CONCURRENT_PAYING_TRANSACTION_LIMIT        => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '10'
            ],
            "hidden" => true
        ],
        FeatureToggle::DISABLE_NON_DEPOSIT_PROVIDER                        => [
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
            "hidden" => false
        ],
        FeatureToggle::PAYING_TIMEOUT_JS_COUNTDOWN                         => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '180',
                "unit" => "秒"
            ],
            "hidden" => true
        ],
        FeatureToggle::CANCEL_PAUFEN_MECHANISM             => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '0'
            ],
            "hidden" => true
        ],
        FeatureToggle::LOGIN_THROTTLE                                      => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '3',
                "unit" => "次"
            ],
            "hidden" => false
        ],
        FeatureToggle::TRANSACTION_REWARD                                  => [
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => ''
            ],
            "hidden" => false
        ],
        FeatureToggle::AUTO_DISABLE_NON_LOGIN_USER                         => [
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => ''
            ],
            "hidden" => false
        ],
        FeatureToggle::NOTIFY_NON_SUCCESS_USER_CHANNEL_ACCOUNT             => [
            'enabled' => true,
            'input'   => [
                'type'  => 'text',
                'value' => '5',
                "unit" => "笔"
            ],
            "hidden" => false
        ],
        FeatureToggle::MAX_AMOUNT_TO_START_FLOATING                        => [
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '2000',
                "unit" => "元"
            ],
            "hidden" => true
        ],
        FeatureToggle::EXCHANGE_MODE                                       => [
            'hidden'  => true,
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '0'
            ],
        ],
        FeatureToggle::NO_FLOAT_IN_WITHDRAWS                               => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ],
        ],
        FeatureToggle::TRANSACTION_MATCH_TYPE       => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '0'
            ],
        ],
        FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT                    => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '100000',
                "unit" => "元"
            ],
        ],
        FeatureToggle::MAX_PROVIDER_HIGH_QUALITY_DEPOSIT_COUNT             => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '2',
                "unit" => "笔"
            ],
        ],

        FeatureToggle::NOTIFICATION_CHECK_REAL_NAME_AND_CARD               => [
            'hidden'  => true,
            'enabled' => true,
            'input'   => [
                'type'  => 'text',
                'value' => '0'
            ],
        ],
        FeatureToggle::DISABLE_TRANSACTION_IF_PAYING_TIMEOUT               => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '5',
                "unit" => "笔"
            ],
        ],
        FeatureToggle::SHOW_DELETED_DATA                                   => [
            'hidden'  => true,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ]
        ],
        FeatureToggle::ALLOW_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT       => [
            'hidden'  => false,
            'enabled' => true,
            'input'   => [
                'type'  => 'boolean',
                'value' => ''
            ]
        ],
        FeatureToggle::VISIABLE_DAYS_OF_PROVIDER_TRANSACTIONS              => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '30',
                'unit'  => '天'
            ]
        ],
        FeatureToggle::VISIABLE_TODAYS_PROVIDER_TRANSACTIONS_AMOUNT        => [
            'hidden'  => true,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => '0'
            ]
        ],
        FeatureToggle::AGENT_WITHDRAW_PROFIT                    => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'value' => ''
            ]
        ],
        FeatureToggle::WITHDRAWS_SYNC_TO_MALL_ORDERS                       => [
            'hidden'  => true,
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => ''
            ]
        ],
        FeatureToggle::MESSAGE_FEATURES_SWITCH                             => [
            'hidden'  => true,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '0'
            ]
        ],
        FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT                  => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'value' => '100000',
                'unit'  => '元'
            ],
        ],
        FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE                 => [
            'hidden'  => false,
            'enabled' => true,
            'input'   => [
                'type'  => 'boolean',
                'value' => ''
            ],
        ],
        FeatureToggle::IF_THIRDCHANNEL_DAIFU_FIAL_THAN_ORDER_FAIL => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '0'
            ]
        ],
        FeatureToggle::WITHDRAW_BANK_NAME_MAPPING => [
            'hidden'  => true,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '0'
            ]
        ],
        FeatureToggle::TRY_NEXT_IF_THIRDCHANNEL_DAIFU_FAIL => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '0'
            ]
        ],
        FeatureToggle::SHOW_THIRDCHANNEL_BALANCE_FOR_MERCHANT => [
            'hidden'  => true,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '0'
            ]
        ],
        FeatureToggle::NOTIFY_ADMIN_THIRD_CHANNEL_BALANCE => [
            'hidden'  => true,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '0'
            ]
        ],
        FeatureToggle::AUTO_DAIFU => [
            'hidden'  => true,
            'enabled' => false,
            'input'   => [
                'type'  => 'text',
                'unit'  => '',
                'value' => '0'
            ]
        ],
        FeatureToggle::MULTI_DEVICES_LOGIN => [
            'hidden'  => false,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '1'
            ]
        ],
        FeatureToggle::USDT_ADD_RATE => [
            'hidden'  => true,
            'enabled' => false,
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '1'
            ]
        ],
        FeatureToggle::PROVIDER_TRANSACTION_CHECK_AMOUNT_FREQUENCY => [
            'hidden' => false,
            'enabled' => true,
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '1'
            ]
        ],
        FeatureToggle::ALLOW_QR_ALIPAY_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT => [
            'hidden' => false,
            'enabled' => true,
            'input'   => [
                'type'  => 'boolean',
                'unit'  => '',
                'value' => '1'
            ]
        ],
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $featureToggles = collect($this->features)->map(function ($feature, $key) {
            return array_merge(
                [
                    'hidden' => false,
                ],
                $feature,
                [
                    'id'         => $key,
                    'input'      => json_encode($feature['input']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        })
            ->values()
            ->toArray();

        FeatureToggle::insertIgnore($featureToggles);
    }
}

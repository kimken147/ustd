<?php

use App\Model\Permission;
use App\Model\User;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{

    const GROUP_NAME_PROVIDER = '码商管理';
    const GROUP_NAME_MERCHANT = '商户管理';
    const GROUP_NAME_USER_CHANNEL_ACCOUNT = '收款帐号管理';
    const GROUP_NAME_TRANSACTION = '交易管理';
    const GROUP_NAME_SYSTEM_BANK_CARD = '系统银行卡管理';
    const GROUP_NAME_DEPOSIT = '码商充值';
    const GROUP_NAME_WITHDRAW = '提现管理';
    const GROUP_NAME_CHANNEL = '通道管理';
    // const GROUP_NAME_WHITELISTED_IP = '管理员白名单设置';
    // const GROUP_NAME_TIME_LIMIT_BANK = '夜间银行限制设置';
    const GROUP_NAME_BANNEDED_IP = '黑名单设置';
    const GROUP_NAME_FEATURE_TOGGLE = '系统设置';
    const GROUP_NAME_THIRD_CHANNEL = '三方管理';
    const GROUP_NAME_AUTHORITY_MANAGE = '权限管理';

    private $permissions = [
        Permission::ADMIN_CREATE_PROVIDER                         => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_PROVIDER,
            'name'       => '新增码商',
        ],
        Permission::ADMIN_UPDATE_PROVIDER                         => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_PROVIDER,
            'name'       => '编辑码商基本资料',
        ],
        Permission::ADMIN_CREATE_MERCHANT                         => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_MERCHANT,
            'name'       => '新增商户',
        ],
        Permission::ADMIN_UPDATE_MERCHANT                         => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_MERCHANT,
            'name'       => '编辑商户基本资料',
        ],
        Permission::ADMIN_UPDATE_USER_CHANNEL_ACCOUNT             => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_USER_CHANNEL_ACCOUNT,
            'name'       => '编辑收付款帐号',
        ],
        Permission::ADMIN_DESTROY_USER_CHANNEL_ACCOUNT            => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_USER_CHANNEL_ACCOUNT,
            'name'       => '删除收付款帐号',
        ],
        Permission::ADMIN_UPDATE_TRANSACTION                      => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_TRANSACTION,
            'name'       => '编辑收款订单',
        ],
        Permission::ADMIN_CREATE_SYSTEM_BANK_CARD                 => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_DEPOSIT,
            'name'       => '新增一般银行卡',
        ],
        Permission::ADMIN_UPDATE_SYSTEM_BANK_CARD                 => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_DEPOSIT,
            'name'       => '编辑一般银行卡',
        ],
        Permission::ADMIN_DESTROY_SYSTEM_BANK_CARD                => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_DEPOSIT,
            'name'       => '删除一般银行卡',
        ],
        Permission::ADMIN_UPDATE_DEPOSIT                          => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_DEPOSIT,
            'name'       => '编辑充值订单',
        ],
        Permission::ADMIN_UPDATE_WITHDRAW                         => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_WITHDRAW,
            'name'       => '编辑提现订单',
        ],
        Permission::ADMIN_UPDATE_USER_BANK_CARD                   => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_WITHDRAW,
            'name'       => '管理商户银行卡',
        ],
        Permission::ADMIN_DESTROY_USER_BANK_CARD                  => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_WITHDRAW,
            'name'       => '删除商户银行卡',
        ],
        Permission::ADMIN_UPDATE_PROVIDER_WALLET                  => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_PROVIDER,
            'name'       => '编辑码商钱包',
        ],
        Permission::ADMIN_UPDATE_MERCHANT_WALLET                  => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_MERCHANT,
            'name'       => '编辑商户钱包',
        ],
        // Permission::ADMIN_MANAGE_WHITELISTED_IP                   => [
        //     'role'       => User::ROLE_ADMIN,
        //     'group_name' => self::GROUP_NAME_WHITELISTED_IP,
        //     'name'       => '管理员白名单',
        // ],
        // Permission::ADMIN_MANAGE_TIME_LIMIT_BANK                  => [
        //     'role'       => User::ROLE_ADMIN,
        //     'group_name' => self::GROUP_NAME_TIME_LIMIT_BANK,
        //     'name'       => '管理夜间银行限制',
        // ],
        Permission::ADMIN_MANAGE_MATCHING_DEPOSIT_REWARD          => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_DEPOSIT,
            'name'       => '管理快充奖励',
        ],
        Permission::ADMIN_MANAGE_TRANSACTION_REWARD               => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_TRANSACTION,
            'name'       => '管理交易奖励',
        ],
        Permission::ADMIN_MANAGE_PROVIDER_WHITELISTED_IP          => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_PROVIDER,
            'name'       => '管理码商登录白名单',
        ],
        Permission::ADMIN_MANAGE_MERCHANT_LOGIN_WHITELISTED_IP    => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_MERCHANT,
            'name'       => '管理商户登录白名单',
        ],
        Permission::ADMIN_MANAGE_MERCHANT_API_WHITELISTED_IP      => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_MERCHANT,
            'name'       => '管理商户 API 白名单',
        ],
        Permission::ADMIN_MANAGE_MERCHANT_MATCHING_DEPOSIT_GROUPS => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_MERCHANT,
            'name'       => '管理代付专线',
        ],
        Permission::ADMIN_MANAGE_MERCHANT_TRANSACTION_GROUPS      => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_MERCHANT,
            'name'       => '管理代收专线',
        ],
        Permission::ADMIN_MANAGE_BANNED_IP                        => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_BANNEDED_IP,
            'name'       => '管理黑名单',
        ],
        Permission::ADMIN_MANAGE_MERCHANT_THIRD_CHANNEL           => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_THIRD_CHANNEL,
            'name'       => '三方通道设置',
        ],
        Permission::ADMIN_UPDATE_CHANNEL                          => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_CHANNEL,
            'name'       => '编辑通道',
        ],
        Permission::ADMIN_UPDATE_FEATURE_TOGGLE                   => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_FEATURE_TOGGLE,
            'name'       => '系统设置',
        ],
        Permission::ADMIN_MANAGE_MERCHANT_SECRET                  => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_MERCHANT,
            'name'       => '重置商户密钥',
        ],
        // Permission::ADMIN_MANAGE_ANNOUNCEMENT                     => [
        //     'role'       => User::ROLE_ADMIN,
        //     'group_name' => self::GROUP_NAME_FEATURE_TOGGLE,
        //     'name'       => '公告管理',
        // ],
        Permission::ADMIN_CREATE_FILL_IN_ORDER                    => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_TRANSACTION,
            'name'       => '建立空单',
        ],
        Permission::ADMIN_FINANCIAL_REPORT                        => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_TRANSACTION,
            'name'       => '财务报表',
        ],
        Permission::ADMIN_SHOW_SENSITIVE_DATA                     => [
            'role'       => User::ROLE_ADMIN,
            'group_name' => self::GROUP_NAME_WITHDRAW,
            'name'       => '显示敏感资料',
        ],
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = now();

        $permissions = collect($this->permissions)->map(function ($permission, $key) use ($now) {
            return array_merge($permission, [
                'id'         => $key,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        })
            ->values()
            ->toArray();

        Permission::insertOnDuplicateKey($permissions);
    }
}

<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Permission extends JsonResource
{

    /**
     * Map database values to translation keys
     *
     * @var array
     */
    private static $translationKeyMap = [
        // Group names
        '码商管理'                 => 'Providers',
        '群组管理'                 => 'Groups',
        '商户管理'                 => 'Merchant',
        '收付款帐号管理'            => 'User channel account',
        '收款帐号管理'              => 'User channel account',
        '交易管理'                 => 'Transaction',
        '充值管理'                 => 'Provider deposit',
        '码商充值'                 => 'Provider deposit',
        '提现管理'                 => 'Provider withdrawal',
        '通道管理'                 => 'Channel',
        '黑名单设置'               => 'Banned',
        '三方管理'                 => 'Third-Party',
        '系统设置'                 => 'System',
        // Permission names (group-name combinations)
        'Providers-新增码商'                         => 'Providers-Add',
        'Providers-编辑码商基本资料'                  => 'Providers-Edit',
        'Providers-编辑码商钱包'                      => 'Providers-Edit Wallet',
        'Providers-管理码商登录白名单'                => 'Providers-White List',
        'Groups-新增群组'                            => 'Groups-Add',
        'Groups-编辑群组基本资料'                     => 'Groups-Edit',
        'Merchant-新增商户'                          => 'Merchant-Add',
        'Merchant-编辑商户基本资料'                   => 'Merchant-Edit',
        'Merchant-编辑商户钱包'                       => 'Merchant-Wallet Edit',
        'Merchant-管理商户登录白名单'                 => 'Merchant-Login White List',
        'Merchant-管理商户 API 白名单'               => 'Merchant-API White List',
        'Merchant-管理代付专线'                       => 'Merchant-PayOut',
        'Merchant-管理代收专线'                       => 'Merchant-PayIn',
        'Merchant-重置商户密钥'                       => 'Merchant-Reset Key',
        'User channel account-编辑收付款帐号'        => 'User channel account-Edit',
        'User channel account-删除收付款帐号'        => 'User channel account-Delete',
        'Transaction-编辑收款订单'                    => 'Transaction-Edit',
        'Transaction-管理交易奖励'                    => 'Transaction-Reward',
        'Transaction-建立空单'                        => 'Transaction-Create Order',
        'Transaction-财务报表'                        => 'Transaction-FinancialStatements',
        'Provider deposit-新增一般银行卡'             => 'Provider deposit-Add bank card',
        'Provider deposit-编辑一般银行卡'             => 'Provider deposit-Edit bank card',
        'Provider deposit-删除一般银行卡'             => 'Provider deposit-Delete bank card',
        'Provider deposit-编辑充值订单'               => 'Provider deposit-Edit deposit list',
        'Provider deposit-管理快充奖励'               => 'Provider deposit-Reward',
        'Provider withdrawal-编辑提现订单'            => 'Provider withdrawal-Edit order',
        'Provider withdrawal-管理商户银行卡'          => 'Provider withdrawal-Merchant bank card',
        'Provider withdrawal-删除商户银行卡'          => 'Provider withdrawal-Delete merchant bank card',
        'Provider withdrawal-显示敏感资料'            => 'Provider withdrawal-Display data',
        'Channel-编辑通道'                           => 'Channel-Edit',
        'Banned-管理黑名单'                          => 'Banned-Management',
        'Third-Party-三方通道设置'                   => 'Third-Party-Setting',
        'Third-Party-三方通道设定'                   => 'Third-Party-Setting',
        'System-系统设置'                            => 'System-Setting',
    ];

    /**
     * Get translation key for group_name
     *
     * @return string
     */
    private function getGroupNameTranslationKey()
    {
        return self::$translationKeyMap[$this->group_name] ?? $this->group_name;
    }

    /**
     * Get translation key for name
     *
     * @return string
     */
    private function getNameTranslationKey()
    {
        $combinedKey = $this->getGroupNameTranslationKey() . '-' . $this->name;
        return self::$translationKeyMap[$combinedKey] ?? $combinedKey;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $groupNameKey = $this->getGroupNameTranslationKey();
        $nameKey = $this->getNameTranslationKey();

        return [
            'id'         => $this->id,
            'group_name' => __('permission.' . $groupNameKey),
            'name'       => __('permission.' . $nameKey),
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property array input
 * @property boolean enabled
 * @property boolean hidden
 */
class FeatureToggle extends Model
{

    const FEATURE_MIN_PROVIDER_MATCHING_BALANCE = 1; //码商最低余额限制
    const FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT = 2;  //跑分提现订单暂停锁定时限
    const FEATURE_MIN_COUNT_REGULAR_CUSTOMER_ENABLED = 3; //熟人模式最低人数限制(用不到)
    const FEATURE_PROCESS_TRANSACTION_NOTIFICATION = 4; // 开启APP 自动回调、自动回调时限
    const FEATURE_MIN_PROVIDER_MATCHING_BALANCE_IN_PERCENT = 5; //每笔代收为余额的
    const PROVIDER_TRANSFER_BALANCE = 6; //允许码商转点可用余额
    const INITIAL_PROVIDER_FROZEN_BALANCE = 7; //码商初始冻结金额
    const NOTIFY_ADMIN_UPDATE_BALANCE = 8; //管端修改余额警示
    const NOTIFY_ADMIN_LOGIN = 9; //管端登录警示
    const NOTIFY_ADMIN_RESET_PASSWORD = 10; //管端重置密码警示
    const NOTIFY_ADMIN_RESET_GOOGLE2FA_SECRET = 11; //管端重置谷歌验证码警示
    const DISABLE_SHOWING_ACCOUNT_ON_QR_ALIPAY_MATCHED_PAGE = 12; //取消支转支复制转账模式
    const MIN_PROVIDER_NORMAL_DEPOSIT_AMOUNT = 13; //码商一般充值单笔最低金额
    const DISABLE_PROVIDER_CREATE_NEW_MEMBER = 14; //禁止码商自行建立下级帐号
    const TRANSACTION_CREATION_RATE_LIMIT = 15; //10 分内允许同 IP 交易笔数
    const MAX_PROVIDER_NORMAL_DEPOSIT_AMOUNT = 16; //码商一般充值单笔最高金额
    const LATE_NIGHT_BANK_LIMIT = 17; //码商收款号银行使用时段限制 (用不到)
    const DISABLE_SHOWING_QR_CODE_ON_QR_ALIPAY_MATCHED_PAGE = 18; //取消支转支二维码扫码
    const ENABLE_AGENCY_WITHDRAW = 19; //允许全站商户提交代付
    const MATCHING_DEPOSIT_REWARD = 20; //快充奖励相关功能
    const DISABLE_SHOWING_QR_CODE_ON_ALIPAY_BANK_MATCHED_PAGE = 21; //取消支转银二维码扫码
    const PROVIDER_CONCURRENT_PAYING_TRANSACTION_LIMIT = 22; //码商交易并发笔数上限
    const DISABLE_NON_DEPOSIT_PROVIDER = 23; //码商 24 小时内未充值自动停用
    const PAYING_TIMEOUT_JS_COUNTDOWN = 24; //支转银支付时限倒数
    const CANCEL_PAUFEN_MECHANISM = 25; // 免签模式
    const LOGIN_THROTTLE = 26; //登入错误次数限制
    const TRANSACTION_REWARD = 27; //码商交易奖励相关功能
    const AUTO_DISABLE_NON_LOGIN_USER = 28; //停用三天未登录码商帐号
    const NOTIFY_NON_SUCCESS_USER_CHANNEL_ACCOUNT = 29; //码商收款号连续支付超时警示
    const MAX_AMOUNT_TO_START_FLOATING = 30; //超过金额以上不浮动 (不使用)
    const EXCHANGE_MODE = 31; //交易所 (未開發)
    const NO_FLOAT_IN_WITHDRAWS = 32; //提现禁止小数点
    const USDT_ADD_RATE = 33; //全站USDT汇率微调
    const TRANSACTION_MATCH_TYPE = 34; // 交易匹配模式
    const USER_CHANNEL_ACCOUNT_DAILY_LIMIT = 35; //当日各收/出款号额度
    const MAX_PROVIDER_HIGH_QUALITY_DEPOSIT_COUNT = 36; //码商跑分充值数量上限
    const NOTIFICATION_CHECK_REAL_NAME_AND_CARD = 37; //APP 监控短信实名自动回调 (暫時用不到)
    const DISABLE_TRANSACTION_IF_PAYING_TIMEOUT = 38; //收款号支付超时关闭交易
    const SHOW_DELETED_DATA = 39; //显示收付款号删除记录
    const ALLOW_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT = 40; //允许收款帐号相同金额并发
    const VISIABLE_DAYS_OF_PROVIDER_TRANSACTIONS = 41; //码商端仅显示近期交易天数
    const VISIABLE_TODAYS_PROVIDER_TRANSACTIONS_AMOUNT = 42; //码商管理个别显示当日总帐资料 (用不到)
    const AGENT_WITHDRAW_PROFIT = 43; //下发/代付上级返利
    const WITHDRAWS_SYNC_TO_MALL_ORDERS = 44; // 同步代付订单到商城(用不到了)
    const USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT = 45; // 当月各收/出款号额度
    const RECORD_USER_CHANNEL_ACCOUNT_BALANCE = 46; // 记录收/出款帐号余额
    const MESSAGE_FEATURES_SWITCH = 47; //即时讯息相关功能
    const IF_THIRDCHANNEL_DAIFU_FIAL_THAN_ORDER_FAIL = 48; //三方代付失败则订单失败
    const WITHDRAW_BANK_NAME_MAPPING = 49; //代付只允许系统支持银行
    const TRY_NEXT_IF_THIRDCHANNEL_DAIFU_FAIL = 50; //三方代付失败则试下一个三方
    const SHOW_THIRDCHANNEL_BALANCE_FOR_MERCHANT = 51; //商户端显示已启用三方余额 (用不到)
    const NOTIFY_ADMIN_THIRD_CHANNEL_BALANCE = 52; //管端三方余额警示
    const AUTO_DAIFU = 53; // 自動代付
    const MULTI_DEVICES_LOGIN = 54; //管端帐号允许多设备登录
    const PROVIDER_TRANSACTION_CHECK_AMOUNT_FREQUENCY = 64; //码商端收款金额核对检查机制(3次)
    const ALLOW_QR_ALIPAY_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT = 65; //允许支付寶收款帐号相同金额并发

    const INPUT_TYPE_TEXT = 'text';
    const INPUT_TYPE_BOOLEAN = 'boolean';

    protected $casts = [
        'enabled' => 'boolean',
        'input'   => 'json',
    ];

    protected $fillable = ['id', 'hidden', 'enabled', 'input'];

    public function getInput($key, $default = null)
    {
        return data_get($this->input, $key, $default);
    }
}

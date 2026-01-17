<?php

namespace App\Jobs;

use App\Models\UserChannelAccount;
use App\Models\Device;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\Notification;
use App\Repository\FeatureToggleRepository;
use App\Utils\TransactionUtil;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;

class ProcessCnTransactionNotification implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Device
     */
    public $device;

    /**
     * @var string
     */
    public $payload;

    /**
     * Create a new job instance.
     *
     * @param  Device  $device
     * @param  string  $payload
     */
    public function __construct(Device $device, string $payload)
    {
        $this->device = $device;
        $this->payload = $payload;
    }

    /**
     * Execute the job.
     *
     * @param  TransactionUtil  $transactionUtil
     * @return void
     */
    public function handle(TransactionUtil $transactionUtil, FeatureToggleRepository $featureToggleRepository)
    {
        $provider = $this->device->user;

        if (!$featureToggleRepository->enabled(FeatureToggle::FEATURE_PROCESS_TRANSACTION_NOTIFICATION, true)) {
            Log::info(self::class.' disabled', compact('provider'));

            return;
        }

        $payload = json_decode($this->payload);

        if (!$payload) {
            Log::info(self::class.'invalid payload', [
                'payload'  => $this->payload,
                'provider' => $provider,
            ]);

            return;
        }

        $title = data_get($payload, 'title', 'none');
        $message = data_get($payload, 'message', 'none');

        $existsIn5Mins = Notification::where('notification', "{$title}:{$message}")
            ->where('device_id', $this->device->id)
            ->where('created_at', '>=', Carbon::now()->subMinutes(5))
            ->exists();

        if ($existsIn5Mins) {
            Log::info(self::class.' duplicate notification', [
                'message'  => $message,
            ]);

            return;
        }

        $deviceId = !isset($this->device->id) ? 0 : $this->device->id;
        $notification = Notification::create(['notification' => $message, 'device_id' => $deviceId]);

        $appName = strtolower(data_get($payload, 'app_name', 'none'));

        $matches = $this->extractData($appName, $title, $message);

        Log::debug(
            self::class,
            compact('payload', 'message', 'matches')
        );

        foreach ($matches as $match) {
            $notifiedAt = Carbon::make(data_get($payload, 'notified_at'))->tz(config('app.timezone'));
            $provider = $this->device->user;
            $deviceName = $this->device->name;

            $query = Transaction::where([
                ['from_id', $provider->getKey()],
                ['floating_amount', $match['amount']],
                ['from_device_name', $deviceName],
                ['type', Transaction::TYPE_PAUFEN_TRANSACTION],
            ])
            ->whereIn('from_channel_account->bank_name', $match['banks'])
            ->whereIn('channel_code', $match['channelCodes'])
            ->whereIn('status', [Transaction::STATUS_PAYING, Transaction::STATUS_PENDING_REVIEW]);

            if ($featureToggleRepository->enabled(FeatureToggle::FEATURE_PROCESS_TRANSACTION_NOTIFICATION, true)) {
                $query->where('matched_at', '>', $notifiedAt->subSeconds($featureToggleRepository->valueOf(FeatureToggle::FEATURE_PROCESS_TRANSACTION_NOTIFICATION, null, true)));
            }

            if ($featureToggleRepository->enabled(FeatureToggle::NOTIFICATION_CHECK_REAL_NAME_AND_CARD, true)) {
                $query->where('to_channel_account->real_name', $match['realname']);
            }

            $transactions = $query->take(2)->get();

            Log::debug(__METHOD__, [
                'transactions_count' => count($transactions),
                'transaction_id' => $transactions->pluck('id'),
                'transaction_query' => Str::replaceArray('?', $query->getBindings(), $query->toSql())
            ]);

            if ($transactions->count() == 1) {
                $cardNumber = $transactions[0]->from_channel_account['bank_card_number'];

                if (!$this->bankNumberMatch($match['banks'], $cardNumber, $match['card'])) {
                    Log::debug(__METHOD__, compact('cardNumber', 'match'));
                    return 0;
                }

                Log::debug(__METHOD__, [
                    'payload'        => $payload,
                    'transaction_id' => $transactions->first()->getKey(),
                    'matched'        => 1,
                ]);

                $transactionUtil->markAsSuccess($transactions->first(), null, true);

                $user_channel_account = UserChannelAccount::where('id',$transactions->first()->from_channel_account_id)->first();

                // 根據短信的內容，取得卡片餘額，並寫入 balance
                if(bcadd($user_channel_account->balance,$match['amount'],2) != $match['balance']){
                    $notification->update(['transaction_id' => $transactions->first()->id ,'error' => 1 ,'need' => bcadd($user_channel_account->balance,$match['amount'],2) ,'but' => $user_channel_account->balance]);
                }else{
                    $notification->update(['transaction_id' => $transactions->first()->id]);
                }
                $user_channel_account->balance = bcadd($user_channel_account->balance, $match['amount'], 2);
                $user_channel_account->save();

                return;
            }
        }
    }

    private function extractData(string $appName, string $title, string $message)
    {
        switch ($appName) {
            case 'alipay':
                $channelCodes = ['QR_ALIPAY'];
                $amount = 0; // make this job ignore alipay
                return [$channelCodes, $amount];
            case 'yfb':
                $channelCodes = ['QR_YFB'];
                $amount = 0;
                return [$channelCodes, $amount];
            case 'wechatpay':
                $channelCodes = ['QR_WECHATPAY'];
                $amount = 0;
                return [$channelCodes, $amount];
            default:
                return array_map(function ($data) {
                    $data['channelCodes'] = ['BANK_CARD', 'ALIPAY_BANK'];
                    return $data;
                }, $this->extractFromBankSms($title, $message));
        }
    }

    private function extractAmount(string $message, $default = 0)
    {
        $matches = [];

        return (preg_match('{(\d+\.?\d*)}', $message, $matches) === 1
            ? $matches[0]
            : $default
        );
    }

    private function bankNumberMatch($banks, $cardNumber, $smsCard) {
        if (in_array('兴业银行', $banks)) { // 匹配倒数第
            $range = [-strlen($smsCard) - 1, strlen($smsCard)];
        } else {
            $range = [-strlen($smsCard), strlen($smsCard)];
        }

        $cardNumber = str_replace([' ', '-'], '', $cardNumber);
        return substr($cardNumber, $range[0], $range[1]) == $smsCard;
    }

    private function extractFromBankSms(string $title, string $message)
    {
        $title = trim(Str::after($title, '+86'));

        $banks = [
            95599          => [
                'name' => ['农业银行'],
                'regex' => ['/.*【(?<bank>.*?)】(?<name>.*?)于.*?尾号(?<card>\d+).*转(存|账)交易人民币(?<amount>.*?)，.*余额(?<balance>.*)。/'],
            ],
            95588          => [
                'name' => ['工商银行'],
                'regex' => [
                    '/.*尾号(?<card>\d+).*收入[^\d]*(?<amount>.*?)元，(余额(?<balance>.*?)元)?.*对方户名：(?<name>.*?)，.*/',
                    '/.*尾号(?<card>\d+).*收入[^\d]*(?<amount>.*?)元，(余额(?<balance>.*?)元).*/'
                ],
            ],
            95533          => [
                'name' => ['建设银行'],
                'regex' => ['/(?<name>[^\d]*).*尾号(?<card>\d+).*存入人民币(?<amount>.*?)元,活期余额(?<balance>.*?)元.*/'],
            ],
            95566          => [
                'name' => ['中国银行'],
                'regex' => [
                    '/.*账户(?<card>\d+).*收入.*人民币(?<amount>.*?)元.*余额(?<balance>.*?).*/',
                    '/.*收入.*人民币(?<amount>.*?)元.*余额(?<balance>.*?)【(?<bank>.*?)】/'
                ],
            ],
            956056         => [
                'name' => ['天津银行'],
                'regex' => ['/【(?<bank>.*?)】.*（尾数(?<card>\d{6})）.*入账存入.*人民币(?<amount>.*?)元.*/'],
            ],
            956033         => [
                'name' => ['东莞银行'],
                'regex' => ['/【(?<bank>.*?)】.*\[(?<card>.*?)\].*转帐收入人民币(?<amount>.*?)元.*人民币(?<balance>.*?)元.*/'],
            ],
            106980095302         => [
                'name' => ['南京银行'],
                'regex' => ['/【(?<bank>.*?)】.*尾号(?<card>\d+).*由(?<name>.*?)汇入的(?<amount>.*?)元.*余额(?<balance>.*?)，.*/'],
            ],
            95555          => [
                'name' => ['招商银行'],
                'regex' => ['/【(?<bank>.*?)】您账户(?<card>\d+).*转入人民币(?<amount>.*?)，(余额(?<balance>[\d|.]*?)，)?付方(?<name>\p{Han}*)/u'],
            ],
            95577          => [
                'name' => ['华夏银行'],
                'regex' => [],
            ],
            95511          => [
                'name' => ['平安银行'],
                'regex' => ['/.*【(?<bank>.*?)】.*账户(?<card>\d+).*转入人民币(?<amount>.*?)元.*/'],
            ],
            106927995511   => [
                'name' => ['平安银行'],
                'regex' => ['/.*【(?<bank>.*?)】.*账户(?<card>\d+).*转入人民币(?<amount>.*?)元.*/'],
            ],
            106918195511   => [
                'name' => ['平安银行'],
                'regex' => ['/.*【(?<bank>.*?)】.*账户(?<card>\d+).*转入人民币(?<amount>.*?)元.*/'],
            ],
            10693795511 => [
                'name' => ['平安银行'],
                'regex' => ['/.*【(?<bank>.*?)】.*账户(?<card>\d+).*转入人民币(?<amount>.*?)元.*/'],
            ],
            1069156895511 => [
                'name' => ['平安银行'],
                'regex' => ['/.*【(?<bank>.*?)】.*账户(?<card>\d+).*转入人民币(?<amount>.*?)元.*/'],
            ],
            95528          => [
                'name' => ['浦发银行'],
                'regex' => ['/(?J).*尾号(?<card>\d+).*存入(?<amount>.*?)(\[支付宝-(?<name>.*?)支付宝.*\]|\[.*\](?<name>.*?)\d|\[银联入账:(?<name>.*?)\]).*，可用余额((?<balance>.*?)元|(?<balance>.*?))。.*【(?<bank>.*?)】/'],
            ],
            95595          => [
                'name' => ['光大银行'],
                'regex' => ['/(?<name>.*?)向.*尾号(?<card>\d+).*转入(?<amount>.*?)元.*余额为(?<balance>.*?)元.*\[(?<bank>.*?)\]/'],
            ],
            95580          => [
                'name' => ['中国邮政储蓄银行'],
                'regex' => ['/.*\d(?<name>.*?)账户.*尾号(?<card>\d+).*来账金额(?<amount>.*?)元，余额(?<balance>.*?)元.*/'],
            ],
            95568          => [
                'name' => ['民生银行'],
                'regex' => ['/.*账户\*(?<card>\d+).*存入￥(?<amount>.*?)元.*可用余额(?<balance>.*?)元.*/'],
            ],
            95561          => [
                'name' => ['兴业银行'],
                'regex' => ['/.*(?<card>\d{4}).*收入(?<amount>.*?)元，余额(?<balance>.*?)元.*户名:(?<name>.*?)(（.*)?\[(?<bank>.*?)\]/'],
            ],
            95526          => [
                'name' => ['北京银行'],
                'regex' => ['/.*尾号为(?<card>\d+).*收入(?<amount>.*?)元.*余额(?<balance>.*?)元.*/'],
            ],
            95559 => [
                'name' => ['交通银行'],
                'regex' => [
                    '/贵账户\*(?<card>\d+).*转入资金(?<amount>.*?)元，现余额(?<balance>.*?)元，对方户名：(?<name>.*?)($|，).*/', // 贵账户*0140于2021年09月06日00:08在佛山分行跨行汇款转入资金587.90元，现余额687.90元，对方户名：王凯，附言
                    '/您尾号\*(?<card>\d+).*转入(?<amount>.*?)元,交易后余额为(?<balance>.*?)元。【(?<bank>.*?)】/' // 您尾号*0140的卡于09月06日00:13手机银行交行转入7499.94元,交易后余额为8187.84元。【交通银行】
                ],
            ],
            95508 => [
                'name' => ['广发银行'],
                'regex' => ['/【(?<bank>.*?)】.*尾号(?<card>\d+)卡.*收入人民币(?<amount>.*?)元.*账户(?<name>\D*)(?<balance>.*?)元.*/'],
            ],
            9555801 => [
                'name' => ['中信银行'],
                'regex' => ['/.*【(?<bank>.*?)】.*尾号(?<card>\d+).*存入人民币(?<amount>.*?)元.*/'],

            ],
            106575296588 => [
                'name' => ['徽商银行'],
                'regex' => ['/【(?<bank>.*?)】.*尾号(?<card>\d{4}).*转入(?<amount>.*?)元，余额(?<balance>.*?)元.*对方：(?<name>.*)/'],

            ],
            96669 => [
                'name' => ['长安银行'],
                'regex' => [
                    '/【(?<bank>.*?)】.*尾号为(?<card>\d{4}).*到账.*\)(?<amount>.*?)元,?当前余额(?<balance>.*?)元/',
                    '/.*尾号为(?<card>\d{4}).*到账.*\)(?<amount>.*?)元,?当前余额(?<balance>.*?)元。【(?<bank>.*?)】/'
                ],
            ],
            106980096138 => [
                'name' => ['南海农商银行','广东省农村信用联合社'],
                'regex' => ['/.*您尾数(?<card>\d{4}).*收入人民币(?<amount>.*?)元,余额(?<balance>.*?)元.*(【(?<bank>.*?)】)?/'], // 您尾数4678的卡号09月20日11:04网上转账收入人民币1995.91元,余额11096.35元，本行吸收的本外币存款依照《存款保
            ],
            106550206588 => [
                'name' => ['珠海华润银行'],
                'regex' => ['/.*您尾号为(?<card>\d{4}).*人民币(?<amount>.*?)元,余额为(?<balance>.*?)元.*【(?<bank>.*?)】/'],
            ],
            1069800096699 => [
                'name' => ['广州银行'],
                'regex' => [
                    '/【(?<bank>.*?)】.*尾号为(?<card>\d+).*转入(?<amount>.*?)元.*转账，备注:(?<name>.*?)\).*账户余额为：(?<balance>.*?)元.*/',
                    '/.*尾号为(?<card>\d+).*转入(?<amount>.*?)元.*转账，备注:(?<name>.*?)\).*账户余额为：(?<balance>.*?)元.*/'
                ]
            ],
            96268 => [
                'name' => ['江西农商银行'],
                'regex' => ['/【(?<bank>.*?)】(?<name>.*?)余.*?尾号(?<card>\d+).*账户转入人民币(?<amount>.*?)，.*余额(?<balance>.*)。/'],
            ],
            106905961296669 => [
                'name' => ['安徽农金','安徽省农村信用社联合社'],
                'regex' => ['/【(?<bank>.*?)】您账号(?<card>\d+).*转账收入(?<amount>.*?)元.*余(?<balance>.*?)元.*付款方：(?<name>.*?)】.*/'],
            ],
            1069800096511 => [
                'name' => ['长沙银行'],
                'regex' => ['/【(?<bank>.*?)】您尾号(?<card>\d+).*转账转入(?<amount>.*?)元.*余额(?<balance>.*?)元.*付(方|款人)：(?<name>\D+)/'],
            ],
            95367 => [
                'name' => ['武汉农村商业银行'],
                'regex' => ['/(【(?<bank>.*?)】)?.*尾号(?<card>\d+).*转入(?<amount>.*?)元，余额(?<balance>.*?)元(，付款方(?<name>.*?))?。.*/']
            ],
            10655710035962811 => [
                'name' => ['富邦华一银行'],
                'regex' => ['/【(?<bank>.*?)】您尾号(?<card>\d+).*RMB(?<amount>.*?)元.*到账.*/'],
            ],
            10691605962811 => [
                'name' => ['富邦华一银行'],
                'regex' => ['/【(?<bank>.*?)】您尾号(?<card>\d+).*RMB(?<amount>.*?)元.*到账.*/'],
            ],
            106903298962811 => [
                'name' => ['富邦华一银行'],
                'regex' => ['/【(?<bank>.*?)】您尾号(?<card>\d+).*RMB(?<amount>.*?)元.*到账.*/'],
            ],
            10691633962811 => [
                'name' => ['富邦华一银行'],
                'regex' => ['/【(?<bank>.*?)】您尾号(?<card>\d+).*RMB(?<amount>.*?)元.*到账.*/'],
            ],
            106926559962811 => [
                'name' => ['富邦华一银行'],
                'regex' => ['/【(?<bank>.*?)】您尾号(?<card>\d+).*RMB(?<amount>.*?)元.*到账.*/'],
            ],
            10698000096558 => [
                'name' => ['汉口银行'],
                'regex' => ['/\[(?<bank>.*?)\]您.*-(?<card>\d+).*账户.*转入(?<amount>.*?)元，余额(?<balance>.*?)元.*/'],
            ],
            10691175505528 => [
                'name' => ['汇丰银行'],
                'regex' => ['/【(?<bank>.*?)】.*(转入|入账).*CNY(?<amount>.*?)\+/'],
            ],
            10693877528 => [
                'name' => ['汇丰银行'],
                'regex' => ['/【(?<bank>.*?)】.*(转入|入账).*CNY(?<amount>.*?)\+/'],
            ],
            95541 => [
                'name' => ['渤海银行'],
                'regex' => ['/.*卡(?<card>\d+).*于.*入账人民币(?<amount>.*?)元.*/']
            ]
        ];

        $bankParameters = data_get($banks, $title);

        if (empty($bankParameters)) {
            return [];
        }

        $bankName = $bankParameters['name'];
        return array_map(function ($regex) use ($message, $bankName) {
            preg_match($regex, $message, $matches);
            return [
                'banks' => $bankName,
                'realname' => $matches['name'] ?? '',
                'amount' => str_replace([',', ' '], '', $matches['amount'] ?? ''),
                'balance' => str_replace([',', ' '], '', $matches['balance'] ?? ''),
                'card' => $matches['card'] ?? ''
            ];
        }, $bankParameters['regex']);
    }
}

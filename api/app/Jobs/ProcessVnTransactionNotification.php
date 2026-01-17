<?php

namespace App\Jobs;

use App\Models\UserChannelAccount;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\Notification;
use App\Models\Channel;
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

class ProcessVnTransactionNotification implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $accounts;

    public $payload;

    public function __construct($accounts, string $payload)
    {
        $this->accounts = $accounts;
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
        if (!$featureToggleRepository->enabled(FeatureToggle::FEATURE_PROCESS_TRANSACTION_NOTIFICATION, true)) {
            Log::info(self::class.' disabled');

            return;
        }

        $accounts = explode(',', str_replace(' ', '', $this->accounts));

        $payload = json_decode($this->payload);

        if (!$payload) {
            Log::info(self::class.'invalid payload', [
                'payload'  => $this->payload,
                'accounts' => $this->accounts
            ]);

            return;
        }

        $title = data_get($payload, 'title', 'none');
        $message = data_get($payload, 'message', 'none');

        $existsIn5Mins = Notification::where('notification', "{$title}:{$message}")
            ->where('created_at', '>=', Carbon::now()->subMinutes(5))
            ->exists();

        if ($existsIn5Mins) {
            Log::info(self::class.' duplicate notification', [
                'message'  => $message,
            ]);

            return;
        }

        $notification = Notification::create(['notification' => "{$title}:{$message}"]);

        $apkName = strtolower(data_get($payload, 'apk_name', 'none'));

        $message = preg_replace('/\\r\\n|\\n|\\n\\n/', ' ', $message); // 將 \n \r\n 換成 空白
        $matches = $this->extractData($apkName, $title, $message);

        Log::debug(
            self::class,
            compact('payload', 'message', 'matches')
        );

        foreach ($matches as $match) {
            if (empty($match['realname']) && empty($match['note'])) { // 实名及附言都没有则跳過
                continue;
            }

            $notifiedAt = Carbon::make(data_get($payload, 'notified_at'))->tz(config('app.timezone'));

            $query = Transaction::where([
                ['floating_amount', $match['amount']],
                ['type', Transaction::TYPE_PAUFEN_TRANSACTION],
            ])
            ->whereIn('from_channel_account_hash_id', $accounts)
            ->whereIn('channel_code', $match['channelCodes'])
            ->whereIn('status', [Transaction::STATUS_PAYING, Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_PAYING_TIMED_OUT]);

            // $isMomo = in_array(Channel::CODE_QR_MOMOPAY, $match['channelCodes']);
            // if (!$isMomo) { // 银行短信
            //     $query->whereIn('from_channel_account->bank_name', $match['banks']);
            // }

            if ($featureToggleRepository->enabled(FeatureToggle::FEATURE_PROCESS_TRANSACTION_NOTIFICATION, true)) {
                $query->where('matched_at', '>', $notifiedAt->subSeconds($featureToggleRepository->valueOf(FeatureToggle::FEATURE_PROCESS_TRANSACTION_NOTIFICATION, null, true)));
            }

            if ($featureToggleRepository->enabled(FeatureToggle::NOTIFICATION_CHECK_REAL_NAME_AND_CARD, true)) {
                $query->where('to_channel_account->real_name', $match['realname']);
            }

            $transactions = $query->get();

            Log::debug(__METHOD__, [
                'transactions_count' => count($transactions),
                'transaction_id' => $transactions->pluck('id'),
                'transaction_query' => Str::replaceArray('?', $query->getBindings(), $query->toSql())
            ]);

            foreach ($transactions as $transaction) {
                if (!empty($match['realname']) && $transaction->to_channel_account['real_name'] != $match['realname']) {
                    continue;
                }

                if (!empty($match['note']) && $transaction->note != $match['note']) {
                    continue;
                }

                Log::debug(__METHOD__, [
                    'payload'        => $payload,
                    'transaction_id' => $transaction->getKey(),
                    'matched'        => 1,
                ]);

                $fromPayingTimedOut = false;
                if ($transaction->status == Transaction::STATUS_PAYING_TIMED_OUT) {
                    $fromPayingTimedOut = true;
                }
                $transactionUtil->markAsSuccess($transaction, null, true, $fromPayingTimedOut);
                break;
            }
        }
    }

    private function extractData(string $apkName, string $title, string $message)
    {
        switch ($apkName) {
            case 'com.mservice.momotransfer':
                $channelCodes = ['QR_MOMOPAY'];

                $isMatched = preg_match('/^(\\"(?<note>\d{6})\\"\.\s)?Nhấn để xem chi tiết.$/', $message, $messageMatches);
                $note = $messageMatches['note'] ?? '';

                preg_match('/^Nhận\s(?<amount>.+)đ\stừ\s(?<card>.*)$/', $title, $titleMatches);
                $amount = $isMatched ? str_replace([',', '.', ' '], '', $titleMatches['amount'] ?? 0) : 0;

                $realname = '';

                return [compact('channelCodes', 'amount', 'realname', 'note')];
            case 'com.vnpay.bidv':
                $channelCodes = [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD];

                preg_match('/.*Số tiền:\s\+(?<amount>.+)VND\..*\;.*\;(?<note>\d{6})\s.*/', $message, $messageMatches);
                $amount = str_replace([',', '.', ' '], '', $messageMatches['amount'] ?? '');
                $note = $messageMatches['note'] ?? '';
                $realname = '';
                $banks = ['BIDV'];

                return [compact('channelCodes', 'amount', 'realname', 'note', 'banks')];

            case 'com.vnpay.Agribank': // agr
            case 'com.vnpay.Agribank3g': //agr
                $channelCodes = [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD];

                preg_match('/.*Tài khoản\s(?<bank>.+?):\s\+(?<amount>.+?)VND\..*-(?<not>.+?)\.\sSố dư cuối:\s(?<balance>.+?)VND$/', $message, $messageMatches);
                $amount = str_replace([',', '.', ' '], '', $messageMatches['amount'] ?? '');
                $note = $messageMatches['note'] ?? '';
                $realname = '';
                $banks = ['AgriBank', 'AGR'];

                return [compact('channelCodes', 'amount', 'realname', 'note', 'banks')];

            case 'com.vietinbank.ipay': // vtb
            case 'com.vn.vib.mobileapp': // vib
            case 'com.vib.myvib': // vib
            case 'com.vnpay.eximbank': // eib
            case 'vn.com.techcombank.bb.app': // tcb
            case 'mb': //
            case 'vn.com.msb.smartBanking': // msb
            case 'com.tpb.mb.gprsandroid': // tpb
                $channelCodes = [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD];
                preg_match('/.*TK:\s(?<card>.+?)\sPS:\+(?<amount>.+?)VND\sSD:\s(?<balance>.+?)VND.*\s(?<note>\d{6})$/', $message, $messageMatches);
                $amount = str_replace([',', '.', ' '], '', $messageMatches['amount'] ?? '');
                $note = $messageMatches['note'] ?? '';
                $realname = '';
                $banks = ['TPBank', 'Tien Phong Bank', 'TPB', 'TP'];

                return [compact('channelCodes', 'amount', 'realname', 'note', 'banks')];
            default:
                return $this->extractFromBankSms($title, $message);
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

    private function extractFromBankSms(string $title, string $message)
    {
        $banks = [
            'AGRIBANK' => [
                'name' => ['AgriBank', 'AGR'],
                'regex' => ['/^(?<bank>.*?):.*\sTK\s(?<card>\d+):\s\+(?<amount>.*)VND\s\(.*-(?<note>\d{6})\).*SD:\s(?<balance>.*)VND.*/'],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'ACB' => [
                'name' => ['ACB'],
                'regex' => [
                    '/^(?<bank>.*?)\sTK\s(?<card>\d+)\(VND\).*\+\s(?<amount>.+?)\s.*So\sdu\s(?<balance>.+?)\.\sGD:\s(?<realname>[A-Z\s]+)\s.*\s(?<note>\d{6}?)$/',
                    '/^(?<bank>.*?)\sTK\s(?<card>\d+)\(VND\).*\+\s(?<amount>.+?)\s.*So\sdu\s(?<balance>.+?)\.\sGD:\s(?<realname>[A-Za-z\s]+)\sQR\s(?<note>\d{6}?)\s.*$/'
                ],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'Eximbank' => [
                'name' => ['Exim Bank', 'Eximbank', 'EIB'],
                'regex' => ['/^(?<bank>.*?)\s.*TK\s(?<card>\d+?)\s.*?\s(?<realname>[A-Z\s]+)\s(?<note>\d{6}?)\s\+(?<amount>.+?)\sVND\sSD\s(?<balance>.+)\sVND/'],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'MBBANK' => [
                'name' => ['MB Bank', 'MBBANK', 'MB'],
                'regex' => [
                    '/^TK\s(?<card>\d+)\sGD:\s\+(?<amount>.+?)VND\s.*SD:(?<balance>.+?)VND.*ND:\s.*(?<note>\d{6}?)\s.*/',
                    '/^TK\s(?<card>\d+)\sGD:\s\+(?<amount>.+?)VND\s.*SD:(?<balance>.+?)VND.*ND:\s.*(?<note>\d{6}?)\sCT.*/'
                ],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'Vietcombank' => [
                'name' => ['Vietcombank', 'VCB'],
                'regex' => [
                    '/^SD\sTK\s(?<card>\d+)\s\+(?<amount>.*)VND\s.*SD\s(?<balance>.*)VND\.\sRef\s.+?\..+?\.(?<note>\d{6}?)\..*?\s.*?\s(?<member_card>\d+?)\ (?<realname>[A-Z\s]+)\s.*/',
                    '/^SD\sTK\s(?<card>\d+)\s\+(?<amount>.*)VND\s.*SD\s(?<balance>.*)VND.*(?<note>\d{6}?)/'
                ],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'TPBank' => [
                'name' => ['TPBank', 'Tien Phong Bank', 'TPB', 'TP'],
                'regex' => [
                    '/^\((?<bank>.*?)\):.*TK:\sxxxx(?<card>\d+)\sPS:\+(?<amount>.+?)VND\sSD:\s(?<balance>.+?)VND\sSD\s(?<realname>.+?):.*?:\s(?<note>\d{6}?)$/',
                ],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'VPBank' => [
                'name' => ['VP Bank', 'VPBank', 'VPB'],
                'regex' => [
                    '/^TK\s(?<card>\d+).*\+(?<amount>.+)VND.*So\sdu\s(?<balance>.+)VND.*:(?<note>\d{6})$/',
                    '/^TK\s(?<card>\d+).*\+(?<amount>.+)VND.*So\sdu\s(?<balance>.+)VND.*[ |\.](?<note>\d{6})[ |\.|]/',
                    '/^TK\s(?<card>\d+).*\+(?<amount>.+)VND.*So\sdu\s(?<balance>.+)VND.*ND\s(?<note>\d{6})/'
                ],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'VietinBank' => [
                'name' => ['VietinBank', 'VTB'],
                'regex' => ['/^(?<bank>.*?):.*TK:(?<card>.*?)\|GD:\+(?<amount>.*?)VND\|SDC:(?<balance>.*?)VND\|ND:(?<note>\d{6}?);.*$/'],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'MSB' => [
                'name' => ['MSB'],
                'regex' => [
                    '/^.*TK\s(?<card>.*?)\sVND\s\(\+\)\s(?<amount>.*?)\s\(GD:.*ND:\s-.+?-(?<note>\d{6}?)-.*\s.*SD:\s(?<balance>.+)$/',
                    '/^.*TK\s(?<card>.*?)\sVND\s\(\+\)\s(?<amount>.*?)\s\(GD:.*ND:\s(?<note>\d{6}?)\sSD:(?<balance>.*)$/'
                ],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'VIB' => [
                'name' => ['VIB Bank', 'VIB'],
                'regex' => [
                    '/^.*TK:(?<card>.+?)VND\sPS:\+(?<amount>.+?)\sND:(?<note>\d{6})\sSODU:\+(?<balance>.*)$/',
                    '/^.*TK:(?<card>.+?)VND\sPS:\+(?<amount>.+?)\sND:.*;.*;(?<note>\d{6})\sSODU:\+(?<balance>.*)$/'
                ],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'Techcombank' => [
                'name' => ['TechcomBank', 'TCB'],
                'regex' => ['/^TK\s(?<card>\d+)\s.*GD:\+(?<amount>.+)\sSo\sdu:(?<balance>.+)\s(?<note>\d{6})$/'],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'BIDV' => [
                'name' => ['BIDV'],
                'regex' => ['/.*TK(?<card>.+?)\s.*\+(?<amount>.+?)VND.*So\sdu:(?<balance>.*?)VND.*\s(?<note>\d{6}?)$/'],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ],
            'SCB' => [
                'name' => ['SCB'],
                'regex' => ['/^TK\s(?<card>.*?)\s(?<balance>.*?).*TANG\s(?<amount>.*?)\s.*\sVND\s\((?<note>\d{6}?)\)$/'],
                'channelCodes' => [Channel::CODE_QR_BANK, Channel::CODE_DC_BANK, Channel::CODE_BANK_CARD]
            ]
        ];

        $bankParameters = data_get($banks, $title);

        if (empty($bankParameters)) {
            return [];
        }

        $bankName = $bankParameters['name'];
        $channelCodes =  $bankParameters['channelCodes'];
        return array_map(function ($regex) use ($message, $bankName, $channelCodes) {
            preg_match($regex, $message, $matches);
            return [
                'banks' => $bankName,
                'realname' => $matches['name'] ?? '',
                'amount' => str_replace([',', '.', ' '], '', $matches['amount'] ?? ''),
                'balance' => str_replace([',', '.', ' '], '', $matches['balance'] ?? ''),
                'card' => $matches['card'] ?? '',
                'note' => $matches['note'] ?? '',
                'channelCodes' => $channelCodes
            ];
        }, $bankParameters['regex']);
    }
}

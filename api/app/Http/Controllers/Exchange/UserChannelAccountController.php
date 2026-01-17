<?php

namespace App\Http\Controllers\Exchange;

use App\Http\Controllers\Controller;
use App\Http\Resources\Exchange\UserChannelAccountCollection;
use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\Device;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Utils\GuzzleHttpClientTrait;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserChannelAccountController extends Controller
{
    use GuzzleHttpClientTrait;

    public function destroy(UserChannelAccount $userChannelAccount)
    {
        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER
            || $userChannelAccount->user_id !== auth()->user()->getKey(),
            Response::HTTP_NOT_FOUND
        );

        DB::transaction(function () use ($userChannelAccount) {
            $userChannelAccount->update(['status' => UserChannelAccount::STATUS_DISABLE]);

            $userChannelAccount->transactionGroups()->detach();

            abort_if(
                !$userChannelAccount->delete(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function index()
    {
        /** @var Builder $userChannelAccounts */
        $userChannelAccounts = UserChannelAccount::where('user_id', auth()->user()->getKey())->latest('created_at')
            ->whereHas('channelAmount', function (Builder $channelAmount) {
                $channelAmount->where('channel_code', Channel::CODE_ALIPAY_BANK);
            })
            ->where('status', UserChannelAccount::STATUS_ONLINE);

        return UserChannelAccountCollection::make($userChannelAccounts->paginate());
    }

    public function store(Request $request)
    {
        /** @var ChannelAmount $channelAmount */
        $channelAmount = ChannelAmount::where('channel_code', Channel::CODE_ALIPAY_BANK)->first();

        abort_if(!$channelAmount, Response::HTTP_BAD_REQUEST, '新增失败');

        /** @var UserChannel $userChannel */
        $userChannel = UserChannel::where([
            'user_id'          => auth()->user()->getKey(),
            'channel_group_id' => $channelAmount->channel_group_id,
        ])->first();

        abort_if(!$userChannel, Response::HTTP_BAD_REQUEST, '新增失败');

        abort_if(is_null($userChannel->fee_percent), Response::HTTP_BAD_REQUEST, '未开通');

        $device = ['user_id' => auth()->user()->getKey(), 'name' => '交易所专用'];

        Device::insertIgnore($device);

        $device = Device::where($device)->firstOrFail();

        $userChannelAccount = null;

        $wallet = auth()->user()->wallet;

        if (auth()->user()->depositModeEnabled()) {
            $wallet = User::whereIsRoot()->whereAncestorOrSelf(auth()->user())->firstOrFail()->wallet;
        }

        switch ($channelAmount->channel->present_result) {
            case Channel::RESPONSE_BANK_CARD:
                $this->validate($request, [
                    UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME => 'required',
                    UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER      => 'required',
                    UserChannelAccount::DETAIL_KEY_BANK_NAME             => 'required',
                ]);

                $userChannelAccount = DB::transaction(function () use (
                    $channelAmount,
                    $device,
                    $wallet,
                    $userChannel,
                    $request
                ) {
                    $userChannelAccount = $channelAmount->userChannelAccounts()->create([
                        'user_id'     => auth()->user()->getKey(),
                        'device_id'   => $device->getKey(),
                        'wallet_id'   => $wallet->getKey(),
                        'status'      => UserChannelAccount::STATUS_ONLINE,
                        'fee_percent' => $userChannel->fee_percent,
                        'min_amount'  => $userChannel->min_amount,
                        'max_amount'  => $userChannel->max_amount,
                        'account'     => $request->{UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER},
                        'detail'      => [
                            UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME => $request->{UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME},
                            UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER      => $request->{UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER},
                            UserChannelAccount::DETAIL_KEY_BANK_NAME             => $request->{UserChannelAccount::DETAIL_KEY_BANK_NAME},
                            UserChannelAccount::DETAIL_KEY_ALIPAY_BANK_CODE      => $this->bankCode($request->{UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER}),
                        ],
                    ]);

                    $transactionGroups = TransactionGroup::where(
                        'transaction_type',
                        Transaction::TYPE_PAUFEN_TRANSACTION
                    )
                        ->whereHas('worker', function (Builder $users) {
                            $users->whereAncestorOrSelf(auth()->user());
                        })
                        ->get();

                    $userChannelAccount->transactionGroups()->syncWithoutDetaching($transactionGroups);

                    return $userChannelAccount;
                });
                break;
            default:
                abort(Response::HTTP_BAD_REQUEST, __('common.Please check your input'));
        }

        abort_if(!$userChannelAccount, Response::HTTP_INTERNAL_SERVER_ERROR);

        return \App\Http\Resources\Exchange\UserChannelAccount::make($userChannelAccount);
    }

    private function bankCode($bankCardNumber)
    {
        $fullCardNumberCacheKey = "bank_card_number_to_bank_code_$bankCardNumber";

        $binCodeOfBankCardNumber = Str::substr($bankCardNumber, 0, 6);
        $binCodeCacheKey = "bank_card_number_to_bank_code_$binCodeOfBankCardNumber";

        // 若 BinCode 快取命中，代表曾經有正確的查詢結果，就不需要再發送一次 API，避免被 Alipay 禁止
        if ($cachedBankName = Cache::get($binCodeCacheKey)) {
            return $cachedBankName;
        }

        // 有整張卡號快取的記錄，代表該卡號已經查過，且不是正確的卡號，避免重複發送到 Alipay
        // 這邊用 Has 的原因是 cache 內容是空字串，會直接被當 falsy 判掉
        if (Cache::has($fullCardNumberCacheKey)) {
            return Cache::get($fullCardNumberCacheKey);
        }

        // 沒有命中時，發送 Alipay API 查詢
        try {
            $response = $this->makeClient()
                ->get('https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?'.http_build_query([
                        '_input_charset' => 'utf-8',
                        'cardNo'         => $bankCardNumber,
                        'cardBinCheck'   => 'true',
                    ]));

            $responseData = json_decode($response->getBody()->getContents());

            $bankCode = data_get($responseData, 'bank');

            // 只有 API 查詢結果正確的狀況下，再做 Cache
            if (!empty($bankCode)) {
                Cache::put($binCodeCacheKey, $bankCode, now()->addDay());

                return $bankCode;
            }
        } catch (TransferException $transferException) {
            Log::debug($transferException->getMessage(), [self::class, $bankCardNumber]);

            // 如果是連線問題，不要快取該卡號
            return '';
        }

        Cache::put($fullCardNumberCacheKey, '', now()->addDay());

        return '';
    }
}

<?php

namespace App\Http\Controllers\Country;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\Facades\Queue;
use App\Models\Transaction;
use App\Models\UserChannelAccount;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Utils\TransactionUtil;
use App\Utils\UserChannelAccountUtil;
use App\Utils\BCMathUtil;

class VietnamController extends Controller
{
    public function getTransaction (Request $request, $order)
    {
        if ($request->status) {
            $transaction = Transaction::firstWhere('system_order_number', $order);

            abort_if(!$transaction, 404);

            return response()->json(['status' => $transaction->status]);
        }
        $field = $request->field;

        $transaction = Transaction::firstWhere('system_order_number', $order);
        $to = $transaction->to_channel_account;

        abort_if(!$field || empty($to[$field]), 404);

        return response()->json([$field => $to[$field]]);
    }

    public function updateTransaction (Request $request, TransactionUtil $transactionUtil, $order)
    {
        $transaction = Transaction::firstWhere('system_order_number', $order);

        $to = $transaction->to_channel_account;

        if ($request->has('remove_field')) {
            $fields = explode(',', $request->remove_field);
            foreach ($fields as $field) {
                unset($to[$field]);
            }
        }

        if ($request->has('qrcode')) {
            $to['qrcode'] = $request->qrcode;
        }

        if ($request->has('challenge_code')) {
            $to['challenge_code'] = $request->challenge_code;
        }

        if ($request->has('message')) {
            $to['message'] = $request->message;
        }

        if ($request->has('username') && $request->has('password')) {
            $message = [
                'tx_id' => $transaction->system_order_number,
                'bank' => strtolower($to['bank_name']),
                'receiver_bank' => strtolower($transaction->from_channel_account['bank_name'] ?? explode('_', $transaction->channel->code)[1]),
                'username' => $request->username,
                'password' => $request->password,
                'account' => $transaction->from_channel_account['account'] ?? '',
                'amount' => AmountDisplayTransformer::transform($transaction->floating_amount, ''),
                'real_name' => $to['real_name'],
                'note' => $transaction->note
            ];

            $to['username'] = $request->username;
            $to['password'] = $request->password;
            $to['status'] = 'username_password_processing';

            $transaction->update(['to_channel_account' => $to]);

            Log::info(__METHOD__, compact('message'));
            Queue::connection('sqs')->pushRaw(json_encode($message), config('queue.vn.direct-connect'));

            return redirect()->to($request->back); // 從收銀台更新資料，所以返回收銀台
        }
        if ($request->has('login_otp')) {
            $to['login_otp'] = $request->login_otp;
            $to['status'] = 'login_otp_processing';

            $transaction->update(['to_channel_account' => $to]);

            return redirect()->to($request->back); // 從收銀台更新資料，所以返回收銀台
        }
        if ($request->has('tx_otp')) {
            $to['tx_otp'] = $request->tx_otp;
            $to['status'] = 'tx_otp_processing';

            $transaction->update(['to_channel_account' => $to]);

            return redirect()->to($request->back); // 從收銀台更新資料，所以返回收銀台
        }

        if ($request->has('status')) {
            if ($request->status == 'success') {

            } else if ($request->status == 'fail') {
                $transactionUtil->markAsFailed($transaction, null, $request->input('msg', ''), false);
            } else {
                $to['status'] = $request->status;

                $transaction->update(['to_channel_account' => $to]);
            }
            return response('ok');
        }

        if ($request->has('daifu_account_id')) {
            $account = UserChannelAccount::find($request->daifu_account);
            $transaction->update(['to_channel_account' => $request->daifu_account]);
            return response('ok');
        }

        return abort(400);
    }

    public function updateDaifu(Request $request, TransactionUtil $transactionUtil, UserChannelAccountUtil $accountUtil, $order)
    {
        $math = new BCMathUtil;
        $transaction = Transaction::find($order);

        if ($request->has('img')) { // 上傳電子回單
            $image = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->img));

            $path = Str::random(40);
            Storage::disk('transaction-certificate-files')->put($path, $image);
            $transaction->certificateFiles()->create(['path' => $path]);
        }

        if ($request->has('balance')) {
            $transaction->toChannelAccount->update(['balance' => $request->balance]);
        }

        if ($request->has('status')) {
            if ($request->status == Transaction::STATUS_SUCCESS) {
                $account = $transaction->toChannelAccount;
                $transactionUtil->markAsSuccess($transaction, null, true, false, false);
            }

            if ($request->status == Transaction::STATUS_FAILED) {
                $transactionUtil->markAsFailed($transaction, null, $request->input('msg', ''), false);
            }
        }

        return response('ok');
    }
}

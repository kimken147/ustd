<?php

namespace App\Http\Resources\Provider;

use App\Model\UserChannelAccount;
use App\Model\User as Users;
use App\Utils\AmountDisplayTransformer;
use App\Utils\BCMathUtil;
use App\Model\Notification as Notifications;
use App\Http\Resources\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class Notification extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                               => $this->getKey(),
            'system_order_number'              => $this->whenLoaded('tran')->system_order_number,
            'provider'                         => User::make(Users::where('id',$this->whenLoaded('tran')->from_id)->first()),
            'amount'                           => AmountDisplayTransformer::transform($this->whenLoaded('tran')->floating_amount),
            'provider_channel_account'         => $this->transformDetail($this->whenLoaded('tran')->from_channel_account),
            'provider_channel_account_hash_id' => $this->whenLoaded('tran')->from_channel_account_hash_id,
            'need'  => $this->need,
            'but'   => $this->but,
        ];
    }

    private function randomAmounts($initialAmount)
    {
        /** @var BCMathUtil $bcMath */
        $bcMath = app(BCMathUtil::class);
        $minAmount = $bcMath->subMinZero($initialAmount, 500);
        $maxAmount = $bcMath->add($initialAmount, 500);
        $randomNumbers = collect([$initialAmount]);

        for ($i = 0; $i < 3; $i++) {
            $randomNumbers[] = rand($minAmount, $maxAmount);
        }

        return $randomNumbers->map(function ($amount) {
            return AmountDisplayTransformer::transform($amount);
        })->shuffle();
    }

    private function transformDetail($detail)
    {
        try {
            if ($qrCodeFilePath = data_get($detail,
                UserChannelAccount::DETAIL_KEY_QR_CODE_FILE_PATH)) {
                data_set(
                    $detail,
                    UserChannelAccount::DETAIL_KEY_QR_CODE_FILE_PATH,
                    Storage::disk('user-channel-accounts-qr-code')->temporaryUrl($qrCodeFilePath, now()->addHour())
                );
            }
        } catch (RuntimeException $e) {
            Log::debug($e);

            return $detail;
        }

        return $detail;
    }

    private function getConfirmable()
    {
        return (
            !$this->locked
            && $this->from->isSelfOrDescendantOf(auth()->user())
            && $this->status === \App\Model\Transaction::STATUS_PAYING
            && $this->type === \App\Model\Transaction::TYPE_PAUFEN_TRANSACTION
        );
    }

    private function getLockable()
    {
        // 跑分提現只要被碼商搶到，一律從充值管理處理
        if ($this->type !== \App\Model\Transaction::TYPE_PAUFEN_TRANSACTION) {
            return false;
        }

        return !$this->locked;
    }

    private function getUnlockable()
    {
        if ($this->type !== \App\Model\Transaction::TYPE_PAUFEN_TRANSACTION) {
            return false;
        }

        if (!$this->locked) {
            return false;
        }

        if ($this->lockedBy->is(auth()->user()->realUser())) {
            return true;
        }

        if (optional($this->lockedBy->parent)->is(auth()->user()->realUser())) {
            return true;
        }

        return false;
    }

    private function filteredByRole(int $role)
    {
        return function (TransactionFee $transactionFee) use ($role) {
            if ($role === \App\Model\User::ROLE_ADMIN) {
                return $transactionFee->user_id === 0;
            }

            return optional($transactionFee->user)->role === $role;
        };
    }

    private function filterByDescent()
    {
        return function (TransactionFee $transactionFee) {
            return in_array($transactionFee->user_id, auth()->user()->descendants->pluck('id')->all());
        };
    }
}

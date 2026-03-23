<?php

namespace App\Utils;

use App\DTOs\TransactionParams;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Services\Transaction\TransactionFeeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class TransactionFactory
{
    private $transactionFeeService;
    private $dataBuilder;

    public function __construct(
        TransactionFeeService $transactionFeeService,
        TransactionDataBuilder $dataBuilder
    ) {
        $this->transactionFeeService = $transactionFeeService;
        $this->dataBuilder = $dataBuilder;
    }

    public function normalDepositTo(TransactionParams $params, User $provider)
    {
        $this->throwIfMissing($params, ["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $data = $this->dataBuilder->buildNormalDeposit($params, $provider);
            $transaction = Transaction::create($data);

            if ($params->note) {
                TransactionNote::create([
                    "transaction_id" => $transaction->getKey(),
                    "user_id" => $provider->realUser()->getKey(),
                    "note" => $params->note,
                ]);
            }

            $this->transactionFeeService->createDepositFees($transaction, $provider);

            DB::commit();
        } catch (RuntimeException $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    private function throwIfMissing(TransactionParams $params, array $attributes)
    {
        foreach ($attributes as $attribute) {
            if ($attribute === "bankCard") {
                if (
                    is_null($params->bankCard) ||
                    !($params->bankCard instanceof BankCardTransferObject)
                ) {
                    throw new RuntimeException("bankCard can not be empty");
                }

                foreach ($params->bankCard as $key => $bankCardProperty) {
                    if (in_array($key, ["bankProvince", "bankCity"])) {
                        continue;
                    }
                    throw_if(
                        is_null($bankCardProperty),
                        new RuntimeException($attribute . " can not be empty")
                    );
                }
            } else {
                throw_if(
                    is_null($params->$attribute),
                    new RuntimeException($attribute . " can not be empty")
                );
            }
        }
    }

    public function normalWithdrawFrom(
        TransactionParams $params,
        User $user,
        $agency = false,
        ?Transaction $parent = null,
        $type = "balance"
    ) {
        $this->throwIfMissing($params, ["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $data = $this->dataBuilder->buildNormalWithdraw($params, $user);
            $transaction = Transaction::create($data);

            $this->transactionFeeService->createWithdrawFees(
                $transaction,
                $user,
                $agency,
                $parent,
                $type
            );

            DB::commit();
        } catch (RuntimeException $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    public function paufenWithdrawFrom(
        TransactionParams $params,
        User $merchant,
        $agency = false,
        ?Transaction $parent = null
    ) {
        $this->throwIfMissing($params, ["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $data = $this->dataBuilder->buildPaufenWithdraw($params, $merchant);
            $transaction = Transaction::create($data);

            $this->transactionFeeService->createWithdrawFees(
                $transaction,
                $merchant,
                $agency,
                $parent
            );

            DB::commit();
        } catch (RuntimeException $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollback();
            return null;
        }

        return $transaction;
    }

    public function thirdchannelWithdrawFrom(
        TransactionParams $params,
        User $user,
        $agency = false,
        ?Transaction $parent = null,
        $thirdchannel_id = null
    ) {
        $this->throwIfMissing($params, ["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $data = $this->dataBuilder->buildThirdchannelWithdraw($params, $user, $thirdchannel_id);
            $transaction = Transaction::create($data);

            $this->transactionFeeService->createWithdrawFees($transaction, $user, $agency, $parent);

            DB::commit();
        } catch (RuntimeException $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    /**
     * Create an internal transfer transaction.
     *
     * @param TransactionParams $params Transaction parameters
     * @param UserChannelAccount|null $account Target channel account
     */
    public function internalTransferFrom(TransactionParams $params, ?UserChannelAccount $account = null, string $currency = 'USDT'): ?Transaction
    {
        $this->throwIfMissing($params, ["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $data = $this->dataBuilder->buildInternalTransfer($params, $account, $currency);
            $transaction = Transaction::create($data);

            DB::commit();
        } catch (RuntimeException $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    public function paufenTransactionTo(TransactionParams $params, User $merchant, Channel $channel)
    {
        $this->throwIfMissing($params, ["amount", "clientIpv4"]);

        return DB::transaction(function () use ($params, $merchant, $channel) {
            $data = $this->dataBuilder->buildPaufenTransaction($params, $merchant, $channel);
            $transaction = Transaction::create($data);

            if ($transaction->channel->note_enable) {
                if ($channel->country == "vn") {
                    $transaction->update([
                        "note" => Str::substr(
                            $transaction->system_order_number,
                            -6
                        ),
                    ]);
                } else {
                    $transaction->update([
                        "note" =>
                        str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT)
                    ]);
                }
            }

            return $transaction;
        });
    }
}

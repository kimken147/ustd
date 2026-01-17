<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\Deposit;
use App\Http\Resources\Provider\DepositCollection;
use App\Model\FeatureToggle;
use App\Model\SystemBankCard;
use App\Model\Transaction;
use App\Model\User;
use App\Repository\FeatureToggleRepository;
use App\Utils\AmountDisplayTransformer;
use App\Utils\AtomicLockUtil;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\TransactionFactory;
use AWS;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DepositController extends Controller
{

    public function certificatesPresignedUrl(Transaction $deposit)
    {
        abort_if(
            !in_array($deposit->status, [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]),
            Response::HTTP_BAD_REQUEST,
            '目前状态无法修改电子回单'
        );

        $userId = auth()->user()->getAuthIdentifier();

        return Redis::funnel("funnel-create-certificate-presigned-url-$userId")->limit(1)->then(function () use ($userId
        ) {
            $path = Str::random(40);
            $retryCount = 0;

            while (Storage::disk('transaction-certificate-files')->has($path) && $retryCount <= 10) {
                $path = Str::random(40);
                $retryCount += 1;
            }

            abort_if(
                Storage::disk('transaction-certificate-files')->has($path),
                Response::HTTP_BAD_REQUEST,
                '系统繁忙，请重试'
            );

            // 第二版電子回單使用
            $paths = collect(Cache::pull("certificate-paths-$userId") ?? []);

            $paths->push($path);

            Cache::put("certificate-paths-$userId", $paths, now()->addHour());

            // 暫時維持原樣以確保新舊程式重疊時不會有問題
            Cache::put("certificate-path-owner-$path", auth()->user()->getKey(), now()->addHour());

            $s3 = AWS::createClient('s3');

            $cmd = $s3->getCommand('PutObject', [
                'Bucket' => config('filesystems.disks.transaction-certificate-files.bucket'),
                'Key'    => $path,
            ]);

            $uri = (string) $s3->createPresignedRequest($cmd, '+1 hour')->getUri();

            return response()->json([
                'certificate_file_path'     => $path,
                'certificate_presigned_url' => $uri,
            ]);
        }, function () {
            abort(Response::HTTP_BAD_REQUEST, '请稍候重试');
        });
    }

    public function index(
        Request $request,
        FeatureToggleRepository $featureToggleRepository
    ) {
        $this->validate($request, [
            'started_at' => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'ended_at'   => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'status'     => ['nullable', 'array'],
        ]);

        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        abort_if(
            now()->diffInMonths($startedAt) > 2,
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            $featureToggleRepository->enabled(FeatureToggle::VISIABLE_DAYS_OF_PROVIDER_TRANSACTIONS) &&
            now()->diffInDays($startedAt) > $featureToggleRepository->valueOf(FeatureToggle::VISIABLE_DAYS_OF_PROVIDER_TRANSACTIONS, 30),
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            !$startedAt
            || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            '时间区间最多一次筛选一个月，请重新调整时间'
        );

        $deposits = Transaction::whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT]);

        if (!is_null($request->only_self)) {
            $deposits->where('to_id', auth()->user()->id);
        } else {
            $deposits->whereIn('to_id', auth()->user()->getDescendantsId());
        }

        $deposits->latest()
            ->with(['to', 'certificateFiles', 'transactionNotes' => function($query) {
                $query->where('user_id', auth()->user()->realUser()->getKey());
            }]);

        $deposits->when($request->started_at, function ($builder, $startedAt) {
            $builder->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
        });

        $deposits->when($request->ended_at, function ($builder, $endedAt) {
            $builder->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
        });

        $deposits->when($request->descendant_provider_username_or_name, function ($builder, $descendantProviderUsernameOrName) {
            $builder->whereIn('to_id', function ($query) use ($descendantProviderUsernameOrName) {
                $query->select('id')
                    ->from('users')
                    ->where('name', $descendantProviderUsernameOrName)
                    ->orWhere('username', $descendantProviderUsernameOrName);
            });
        });

        $deposits->when(
            $request->system_order_number,
            function ($builder, $systemOrderNumber) {
                abort_if(
                    !$this->usingSystemOrderNumber($systemOrderNumber),
                    Response::HTTP_BAD_REQUEST,
                    __('common.Invalid format of system order number')
                );

                $builder->ofSystemOrderNumber($systemOrderNumber);
            }
        );

        $deposits->when(
            $request->status,
            function ($builder, $status) {
                $builder->where(function (Builder $builder) use ($status) {
                    $status = collect($status);
                    $payingStatus = $status->filter(function ($status) {
                        return in_array($status, [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]);
                    });

                    if ($payingStatus->isNotEmpty()) {
                        $builder->where(function (Builder $builder) {
                            $builder->where(function (Builder $builder) {
                                $builder->where('to_wallet_settled', false)
                                    ->whereIn('status',
                                        [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
                            })->orWhereIn('status', [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]);
                        });
                    }

                    $successStatus = $status->filter(function ($status) {
                        return in_array($status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
                    });

                    if ($successStatus->isNotEmpty()) {
                        $builder->where(function (Builder $builder) {
                            $builder->where('to_wallet_settled', true)
                                ->whereIn('status',
                                    [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
                        }, null, null, $payingStatus->isEmpty() ? 'and' : 'or');
                    }

                    $otherStatus = $status->filter(function ($status) {
                        return !in_array($status, [
                            Transaction::STATUS_PAYING, Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS,
                            Transaction::STATUS_RECEIVED
                        ]);
                    });

                    if ($otherStatus->isNotEmpty()) {
                        $builder->whereIn('status', $otherStatus,
                            $payingStatus->isEmpty() && $successStatus->isEmpty() ? 'and' : 'or');
                    }
                });
            }
        );

        $stats = (clone $deposits)->first(
            [
                DB::raw(
                    'SUM(amount) AS total_amount'
                ),
            ]
        );

        return DepositCollection::make($deposits->paginate(20))
            ->additional([
                'meta' => [
                    'total_amount' => AmountDisplayTransformer::transform($stats->total_amount ?? '0.00'),
                ]
            ]);
    }

    private function usingSystemOrderNumber($orderNumberOrSystemOrderNumber)
    {
        return Str::startsWith($orderNumberOrSystemOrderNumber, [config('transaction.system_order_number_prefix')]);
    }

    public function show(Transaction $deposit)
    {
        abort_if(!optional($deposit->to)->is(auth()->user()), Response::HTTP_NOT_FOUND);

        return Deposit::make($deposit->load(['to', 'certificateFiles', 'transactionNotes' => function ($query) {
            $query->where('user_id', auth()->user()->realUser()->getKey());
        }]));
    }

    public function store(
        Request $request,
        TransactionFactory $transactionFactory,
        BankCardTransferObject $bankCardTransferObject,
        BCMathUtil $bcMath,
        AtomicLockUtil $atomicLockUtil,
        FeatureToggleRepository $featureToggleRepository
    ) {
        abort_if(
            !auth()->user()->deposit_enable,
            Response::HTTP_BAD_REQUEST,
            __('user.Deposit disabled')
        );

        $this->validate($request, [
            'amount' => 'required|numeric|min:1',
            'note'   => 'nullable|string',
        ]);

        if ($featureToggleRepository->enabled(FeatureToggle::MIN_PROVIDER_NORMAL_DEPOSIT_AMOUNT)) {
            $minDepositAmount = $featureToggleRepository->valueOf(FeatureToggle::MIN_PROVIDER_NORMAL_DEPOSIT_AMOUNT, 0);
            $formattedMinDepositAmount = AmountDisplayTransformer::transform($minDepositAmount);

            abort_if(
                $bcMath->lt($request->amount, $minDepositAmount),
                Response::HTTP_BAD_REQUEST,
                "一般充值单笔最低金额 $formattedMinDepositAmount 元"
            );
        }

        if ($featureToggleRepository->enabled(FeatureToggle::MAX_PROVIDER_NORMAL_DEPOSIT_AMOUNT)) {
            $maxDepositAmount = $featureToggleRepository->valueOf(FeatureToggle::MAX_PROVIDER_NORMAL_DEPOSIT_AMOUNT, 0);
            $formattedMaxDepositAmount = AmountDisplayTransformer::transform($maxDepositAmount);

            abort_if(
                $bcMath->gt($request->amount, $maxDepositAmount),
                Response::HTTP_BAD_REQUEST,
                "一般充值单笔最高金额 $formattedMaxDepositAmount 元"
            );
        }

        $callback = function () use ($request, $transactionFactory, $bankCardTransferObject, $bcMath) {
            abort_if(
                Transaction::where('to_id', auth()->user()->getKey())
                            ->where('type', Transaction::TYPE_NORMAL_DEPOSIT)
                            ->where(function (Builder $transactions) {
                                $transactions->where(function (Builder $transactions) {
                                    $transactions->whereIn('status',
                                        [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                                        ->where('to_wallet_settled', false);
                                })->orWhereIn('status', [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]);
                            })
                            ->where('created_at', '>=', now()->subDay())
                            ->exists(),
                Response::HTTP_BAD_REQUEST,
                __('transaction.Please complete previous deposit')
            );

            $systemBankCard = SystemBankCard::whereHas('nonShareDescendantsUsers', function (Builder $users) {
                $users->where('users.id', auth()->user()->getKey());
            })->orWhereHas('shareDescendantsUsers', function (Builder $users) {
                $users->whereIn('users.id', User::whereAncestorOrSelf(auth()->user())->select(['id']));
            })
                ->published()
                ->hasSufficientBalance($request->amount)
                ->oldestMatched()
                ->first();

            abort_if(
                !$systemBankCard,
                Response::HTTP_BAD_REQUEST,
                __('system-bank-card.No available system bank card now')
            );

            return DB::transaction(function () use (
                $transactionFactory,
                $bankCardTransferObject,
                $request,
                $systemBankCard,
                $bcMath
            ) {
                /** @var Transaction $transaction */
                $transaction = $transactionFactory
                    ->bankCard($bankCardTransferObject->model($systemBankCard))
                    ->amount($request->amount)
                    ->note($request->note)
                    ->normalDepositTo(auth()->user());

                $balance = $bcMath->subMinZero($systemBankCard->balance, $request->amount);

                $updatedRow = SystemBankCard::where([
                    'id'      => $systemBankCard->getKey(),
                    'status'  => SystemBankCard::STATUS_PUBLISHED,
                    'balance' => $systemBankCard->balance,
                ])->update([
                    'balance'         => $balance,
                    'status'          => $bcMath->gtZero($balance) ? SystemBankCard::STATUS_PUBLISHED : SystemBankCard::STATUS_UNPUBLISHED,
                    'published_at'    => $bcMath->gtZero($balance) ? $systemBankCard->published_at : null
                ]);

                abort_if(
                    $updatedRow !== 1,
                    Response::HTTP_BAD_REQUEST,
                    __('common.Conflict! Please try again later')
                );

                return $transaction;
            });
        };

        /** @var Transaction $transaction */
        $transaction = $atomicLockUtil->lock($atomicLockUtil->keyForUserDeposit(auth()->user()), $callback);

        Cache::put('admin_deposits_added_at', now(), now()->addSeconds(60));

        return Deposit::make($transaction->load(['to', 'certificateFiles', 'transactionNotes' => function($query) {
            $query->where('user_id', auth()->user()->realUser()->getKey());
        }]));
    }

    public function update(Request $request, Transaction $deposit)
    {
        abort_if(!optional($deposit->to)->is(auth()->user()), Response::HTTP_NOT_FOUND);

        $this->validate($request, [
            'certificate' => 'nullable|file',
            'note'        => 'nullable|string|max:50',
        ]);

        abort_if(
            $request->note
            && !in_array($deposit->status, [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]),
            Response::HTTP_BAD_REQUEST,
            '目前状态无法修改备注'
        );

        abort_if(
            $request->certificate
            && !in_array($deposit->status, [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]),
            Response::HTTP_BAD_REQUEST,
            '目前状态无法修改电子回单'
        );

        DB::transaction(function () use ($request, $deposit) {
            $this->updateNoteIfPresent($request, $deposit);

            $this->updateCertificateIfPresent($request, $deposit);
        });

        return Deposit::make($deposit->load(['to', 'transactionNotes' => function($query) {
            $query->where('user_id', auth()->user()->realUser()->getKey());
        }]));
    }

    private function updateNoteIfPresent(Request $request, Transaction $deposit)
    {
        if (!$request->note) {
            return $deposit;
        }

        abort_if(
            !$deposit->update([
                'note' => $request->note,
            ]),
            Response::HTTP_INTERNAL_SERVER_ERROR);

        return $deposit;
    }

    private function updateCertificateIfPresent(Request $request, Transaction $deposit)
    {
        // 第一版
        if ($path = $request->input('certificate_file_path')) {
            abort_if(
                auth()->user()->getKey() != Cache::pull("certificate-path-owner-$path"),
                Response::HTTP_BAD_REQUEST,
                '档案名称错误'
            );

            $deposit->update(['certificate_file_path' => $path]);
        }

        $requestedPaths = collect($request->input('certificate_file_paths', []))->unique();

        // 第二版
        if ($requestedPaths->isNotEmpty()) {
            $userId = auth()->user()->getAuthIdentifier();
            $existingPaths = $deposit->certificateFiles->pluck('path');
            $paths = collect(Cache::pull("certificate-paths-$userId") ?? [])->merge($existingPaths);

            abort_if(
                $requestedPaths->intersect($paths)->count() !== $requestedPaths->count(),
                Response::HTTP_BAD_REQUEST,
                '档案名称错误'
            );

            DB::transaction(function () use ($deposit, $requestedPaths) {
                if ($deposit->certificate_file_path) {
                    $deposit->update(['certificate_file_path' => null]);
                }

                $deposit->certificateFiles()->delete();
                $deposit->certificateFiles()->createMany($requestedPaths->map(function ($path) {
                    return compact('path');
                }));
            });

            $deposit->load('certificateFiles');
        }

        return $deposit;
    }
}

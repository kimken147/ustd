<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MerchantTransactionGroupCollection;
use App\Models\Permission;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Models\UserChannelAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class MerchantTransactionGroupController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:'.Permission::ADMIN_MANAGE_MERCHANT_TRANSACTION_GROUPS])->except(['index']);
    }

    public function batchUpdate(Request $request)
    {
        $this->validate($request, [
            'merchant_ids' => 'required|array',
            'provider_ids' => 'required|array',
            'status'       => 'required|int|in:0,1',
        ]);

        $merchantIds = collect($request->input('merchant_ids'));
        $providersData = collect($request->input('provider_ids'));
        $providerIds = $providersData->pluck('id');

        abort_if(
            User::where('role', User::ROLE_MERCHANT)->whereIn('id', $merchantIds)->count() !== $merchantIds->count(),
            Response::HTTP_BAD_REQUEST,
            '商户资料有误'
        );

        abort_if(
            User::where('role', User::ROLE_PROVIDER)->whereIn('id', $providerIds)->count() !== $providerIds->count(),
            Response::HTTP_BAD_REQUEST,
            '码商资料有误'
        );

        if ($request->boolean('status')) {
            // insert
            $now = now();
            $transactionGroupValues = collect();

            foreach ($merchantIds as $merchantId) {
                foreach ($providersData as $provider) {
                    abort_if(
                        TransactionGroup::where([
                            ['transaction_type', Transaction::TYPE_PAUFEN_TRANSACTION],
                            ['owner_id', $merchantId],
                            ['worker_id', $provider['id']],
                        ])->exists(),
                        Response::HTTP_BAD_REQUEST,
                        '代理线重复'
                    );
                }
            }

            foreach ($merchantIds as $merchantId) {
                foreach ($providersData as $provider) {
                    $transactionGroupValues->add([
                        'transaction_type' => Transaction::TYPE_PAUFEN_TRANSACTION,
                        'owner_id'         => $merchantId,
                        'worker_id'        => $provider['id'],
                        'personal_enable'  => $provider['personal'],
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                }
            }

            DB::transaction(function () use ($merchantIds, $providerIds, $transactionGroupValues) {
                TransactionGroup::insertIgnore($transactionGroupValues->toArray());

                $transactionGroups = TransactionGroup::whereIn('owner_id', $merchantIds)->whereIn('worker_id', $providerIds)->get();

                foreach ($transactionGroups as $transactionGroup) {
                    $transactionGroup->userChannelAccounts()->syncWithoutDetaching(
                        UserChannelAccount::whereHas('user', function (Builder $users) use ($transactionGroup) {
                            if ($transactionGroup->personal_enable) {
                                $users->whereDescendantOrSelf($transactionGroup->worker_id);
                            } else {
                                $users->where('id', $transactionGroup->worker_id);
                            }
                        })->pluck('id')
                    );
                }
            });
        } else {
            // delete
            DB::transaction(function () use ($merchantIds, $providerIds) {
                $transactionGroups = TransactionGroup::whereIn('owner_id', $merchantIds)->whereIn('worker_id', $providerIds)->get();

                foreach ($transactionGroups as $transactionGroup) {
                    $transactionGroup->userChannelAccounts()->detach(
                        UserChannelAccount::whereHas('user', function (Builder $users) use ($transactionGroup) {
                            if ($transactionGroup->personal_enable) {
                                $users->whereDescendantOrSelf($transactionGroup->worker_id);
                            } else {
                                $users->where('id', $transactionGroup->worker_id);
                            }
                        })->pluck('id')
                    );
                }

                TransactionGroup::whereIn('owner_id', $merchantIds)
                    ->whereIn('worker_id', $providerIds)
                    ->where('transaction_type', Transaction::TYPE_PAUFEN_TRANSACTION)
                    ->delete();
            });

        }

        return response()->json(null, Response::HTTP_CREATED);
    }

    public function destroy($transactionGroupId)
    {
        /** @var TransactionGroup $transactionGroup */
        $transactionGroup = TransactionGroup::where('transaction_type',
            Transaction::TYPE_PAUFEN_TRANSACTION)->findOrFail($transactionGroupId);

        DB::transaction(function () use ($transactionGroup) {
            DB::table('transaction_group_user_channel_account')->where('transaction_group_id', $transactionGroup->id)->delete();
            $transactionGroup->delete();
        });

        return response()->noContent();
    }

    public function index(Request $request)
    {
        $users = User::where('role', User::ROLE_MERCHANT)->with('transactionGroups.worker');

        $filterNameOrUsername = User::where(function (Builder $users) use ($request) {
            $users->where('name', 'like', "%{$request->input('name_or_username')}%")
                ->orWhere('username', $request->input('name_or_username'));
        })->select('id');

        $users->when($request->filled('name_or_username'), function (Builder $users) use ($filterNameOrUsername) {
            $users->where(function (Builder $users) use ($filterNameOrUsername) {
                $users->whereIn('id', $filterNameOrUsername)
                    ->orWhereHas('transactionGroups.worker',
                        function (Builder $workers) use ($filterNameOrUsername) {
                            $workers->whereIn('id', $filterNameOrUsername);
                        });
            });
        });

        return MerchantTransactionGroupCollection::make($users->paginate(20));
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'merchant_id' => 'required|int',
            'provider_id' => 'required|int',
        ]);

        abort_if(
            !User::where('role', User::ROLE_MERCHANT)->where('id', $request->input('merchant_id'))->exists(),
            Response::HTTP_BAD_REQUEST,
            '查无商户'
        );

        abort_if(
            !User::where('role', User::ROLE_PROVIDER)->where('id', $request->input('provider_id'))->exists(),
            Response::HTTP_BAD_REQUEST,
            '查无码商'
        );

        abort_if(
            TransactionGroup::where([
                ['transaction_type', Transaction::TYPE_PAUFEN_TRANSACTION],
                ['owner_id', $request->input('merchant_id')],
                ['worker_id', $request->input('provider_id')],
            ])->exists(),
            Response::HTTP_BAD_REQUEST,
            '代理线重复'
        );

        DB::transaction(function () use ($request) {
            $isPersonal = $request->input('personal_enable', false); // 代理線

            $transactionGroup = TransactionGroup::create([
                'transaction_type' => Transaction::TYPE_PAUFEN_TRANSACTION,
                'owner_id'         => $request->input('merchant_id'),
                'worker_id'        => $request->input('provider_id'),
                'personal_enable'  => $isPersonal,
            ]);


            $transactionGroup->userChannelAccounts()->syncWithoutDetaching(
                UserChannelAccount::whereHas('user', function (Builder $users) use ($transactionGroup, $isPersonal) {
                    if ($isPersonal) {
                        $users->whereDescendantOrSelf($transactionGroup->worker_id);
                    } else {
                        $users->where('id', $transactionGroup->worker_id);
                    }
                })->pluck('id')
            );
        });

        return response()->json(null, Response::HTTP_CREATED);
    }
}

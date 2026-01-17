<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SubAccount;
use App\Http\Resources\Admin\SubAccountCollection;
use App\Http\Resources\User as UserResource;
use App\Models\FeatureToggle;
use App\Models\Permission;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Utils\NotificationUtil;
use App\Utils\UserUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubAccountController extends Controller
{

    /**
     * @var UserUtil
     */
    private $userUtil;

    public function __construct(UserUtil $user)
    {
        $this->userUtil = $user;
    }

    /**
     * Display a listing of the resource.
     *
     * @param  Request  $request
     * @return SubAccountCollection
     * @throws ValidationException
     */
    public function index(Request $request)
    {
        $this->validate($request, [
            'name_or_username' => 'nullable|string',
            'permissions'      => 'array',
            'permissions.*'    => 'required_with:permissions|int',
        ]);

        // 系統除了 god 以外的管理員都要顯示
        $subAccounts = User::with('permissions')
            ->where('god', false)
            ->where('role', User::ROLE_SUB_ACCOUNT)
            ->whereHas('parent', function (Builder $parent) {
                $parent->where('role', User::ROLE_ADMIN);
            })
            ->latest();

        $subAccounts->when($request->filled('name_or_username'), function ($query) use ($request) {
            $query->where(function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->name_or_username}%")
                    ->orWhere('username', $request->name_or_username);
            });
        });

        $subAccounts->when(!empty($request->permissions), function ($builder) use ($request) {
            $builder->whereHas('permissions', function ($builder) use ($request) {
                $builder->whereIn((new Permission())->getTable().'.id', $request->permissions);
            });
        });

        return SubAccountCollection::make($subAccounts->paginate(20));
    }

    public function resetGoogle2faSecret(
        User $subAccount,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        Request $request
    ) {
        $this->abortIfInvalidRole($subAccount);

        $google2faSecret = DB::transaction(function () use (
            $subAccount,
            $notificationUtil,
            $whitelistedIpManager,
            $request
        ) {
            $subAccount->update([
                'google2fa_secret' => $google2faSecret = $this->userUtil->generateGoogle2faSecret(),
            ]);

            $notificationUtil->notifyAdminResetGoogle2faSecret(auth()->user()->realUser(), $subAccount,
                $whitelistedIpManager->extractIpFromRequest($request));

            return $google2faSecret;
        });

        return UserResource::make($subAccount->load('permissions'))
            ->withCredentials(['google2fa_secret' => $google2faSecret])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    private function abortIfInvalidRole(User $subAccount)
    {
        abort_if(
            $subAccount->role !== User::ROLE_SUB_ACCOUNT,
            Response::HTTP_BAD_REQUEST,
            __('目标使用者非子帐号')
        );
    }

    public function resetPassword(
        User $subAccount,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        Request $request
    ) {
        $this->abortIfInvalidRole($subAccount);

        $password = DB::transaction(function () use ($subAccount, $notificationUtil, $whitelistedIpManager, $request) {
            $subAccount->update([
                'password' => Hash::make($password = $this->userUtil->generatePassword()),
            ]);

            $notificationUtil->notifyAdminResetPassword(auth()->user()->realUser(), $subAccount,
                $whitelistedIpManager->extractIpFromRequest($request));

            return $password;
        });

        return UserResource::make($subAccount->load('permissions'))
            ->withCredentials(['password' => $password])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param  User  $subAccount
     * @return SubAccount
     */
    public function show(User $subAccount)
    {
        $user = auth()->user();
        abort_unless(
            $user->role == User::ROLE_ADMIN || $subAccount->isDescendantOf($user),
            Response::HTTP_BAD_REQUEST,
            '没有权限'
        );

        return SubAccount::make($subAccount);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return SubAccount
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name'          => 'required|max:20',
            'username'      => 'required|max:10',
            'permissions'   => 'array',
            'permissions.*' => 'required_with:permissions|int',
        ]);

        $this->abortIfUsernameNotAlnum($request->username);
        $this->abortIfUsernameAlreadyExists($request->username);

        $password = $this->userUtil->generatePassword();
        $google2faSecret = $this->userUtil->generateGoogle2faSecret();
        $agent = auth()->user();

        abort_if(auth()->user()->realUser()->role != User::ROLE_ADMIN, Response::HTTP_BAD_REQUEST, '最高权限管理员才能设定');

        $subAccount = DB::transaction(function () use ($request, $agent, $password, $google2faSecret) {
            /** @var User $subAccount */
            $subAccount = User::create([
                'role'               => User::ROLE_SUB_ACCOUNT,
                'status'             => User::STATUS_ENABLE,
                'agent_enable'       => false,
                'google2fa_enable'   => true,
                'withdraw_enable'    => false,
                'withdraw_profit_enable' => false,
                'transaction_enable' => false,
                'google2fa_secret'   => $google2faSecret,
                'password'           => Hash::make($password),
                'secret_key'         => $this->userUtil->generateSecretKey(),
                'name'               => $request->name,
                'username'           => $request->username,
                'parent_id'          => $agent->getKey(),
            ]);

            $permissions = $request->input('permissions', []);

            $feature = app(FeatureToggleRepository::class);
            if ($feature->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
                $permissions[] = Permission::ADMIN_CREATE_PROVIDER;
                $permissions[] = Permission::ADMIN_UPDATE_PROVIDER;
            }

            $subAccount->permissions()->sync($permissions);

            return $subAccount;
        });

        return SubAccount::make($subAccount->load('permissions'))
            ->withCredentials(['password' => $password, 'google2fa_secret' => $google2faSecret]);
    }

    private function abortIfUsernameNotAlnum(string $username)
    {
        abort_if(
            !ctype_alnum($username),
            Response::HTTP_BAD_REQUEST,
            __('common.Username can only be alphanumeric')
        );
    }

    private function abortIfUsernameAlreadyExists(string $username)
    {
        abort_if(
            $this->userUtil->usernameAlreadyExists($username),
            Response::HTTP_BAD_REQUEST,
            __('common.Duplicate username')
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  User  $subAccount
     * @return SubAccount
     */
    public function update(Request $request, User $subAccount)
    {
        $this->abortIfInvalidRole($subAccount);

        $this->validate($request, [
            'name'              => 'string|max:20',
            'username'          => 'string|max:10',
            'status'            => ['int', Rule::in(User::STATUS_DISABLE, User::STATUS_ENABLE)],
            'google2fa_enable'  => 'boolean',
            'permissions'       => 'array',
            'permissions.*'     => 'required_with:permissions|int',
        ]);

        abort_if(auth()->user()->realUser()->role != User::ROLE_ADMIN, Response::HTTP_BAD_REQUEST, '最高权限管理员才能设定');

        $subAccount = DB::transaction(function () use ($subAccount, $request) {
            if ($request->filled('name')) {
                $subAccount->name = $request->name;
            }

            if ($request->filled('username')) {
                $this->abortIfUsernameNotAlnum($request->username);
                $this->abortIfUsernameAlreadyExists($request->username);

                $subAccount->username = $request->username;
            }

            if ($request->filled('status')) {
                $subAccount->status = $request->input('status');
            }

            if ($request->filled('google2fa_enable')) {
                $subAccount->google2fa_enable = $request->input('google2fa_enable');
            }

            $subAccount->save();

            if ($request->has('permissions')) {
                $subAccount->permissions()->sync($request->permissions);
            }

            return $subAccount;
        });

        return SubAccount::make($subAccount);
    }
}

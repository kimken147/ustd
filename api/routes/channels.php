<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

// 只有 admin 用戶或 god 用戶可以訂閱代付單通知
Broadcast::channel('admin.withdraws', function (User $user) {
    return $user->role === User::ROLE_ADMIN || $user->god;
});

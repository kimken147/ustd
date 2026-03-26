<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 註冊 broadcasting 認證路由於 /api/v1/broadcasting/auth
        // 使用 JWT middleware 保護 — 只有認證用戶可以授權頻道
        Broadcast::routes(['prefix' => 'api/v1', 'middleware' => ['auth:api']]);

        require base_path('routes/channels.php');
    }
}

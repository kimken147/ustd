<?php

namespace App\Console\Commands;

use App\Models\Bank;
use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\ChannelGroup;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EnvInit extends Command
{
    protected $signature = 'env:init
                            {--skip-admin : 跳過管理員建立}
                            {--skip-merchant : 跳過測試商戶建立}';

    protected $description = '初始化 USDT 環境：建立管理員、商戶、USDT 通道、銀行（互動式）';

    public function handle()
    {
        $skipAdmin = $this->option('skip-admin');
        $skipMerchant = $this->option('skip-merchant');

        $username = '';
        $password = '';
        $superUsername = '';
        $superPassword = '';
        $merchantUsername = '';
        $merchantPassword = '';

        if (!$skipAdmin) {
            $username = $this->ask('租戶管理員帳號', 'admin');
            $password = $this->secret('租戶管理員密碼') ?: 'password';
            $superUsername = $this->ask('超級管理員帳號', 'superadmin');
            $superPassword = $this->secret('超級管理員密碼') ?: 'password';
        }

        if (!$skipMerchant) {
            $merchantUsername = $this->ask('測試商戶帳號', 'testmerchant');
            $merchantPassword = $this->secret('測試商戶密碼') ?: 'password';
        }

        // 載入資料檔
        $channelsFile = database_path('data/channels/usdt.php');
        $banksFile = database_path('data/banks/usdt.php');

        if (!file_exists($channelsFile)) {
            $this->error("找不到通道資料檔：{$channelsFile}");
            return 1;
        }
        if (!file_exists($banksFile)) {
            $this->error("找不到銀行資料檔：{$banksFile}");
            return 1;
        }

        $channels = require $channelsFile;
        $banks = require $banksFile;

        // 顯示摘要
        $this->info('環境初始化摘要（USDT）');
        $this->table(
            ['項目', '數量', '說明'],
            [
                ['管理員', $skipAdmin ? '跳過' : '2', $skipAdmin ? '--skip-admin' : "{$username} + {$superUsername}"],
                ['測試商戶', $skipMerchant ? '跳過' : '1', $skipMerchant ? '--skip-merchant' : $merchantUsername],
                ['通道', count($channels), '冪等（跳過已存在）'],
                ['銀行', count($banks), '冪等（跳過已存在）'],
                ['通道組 + 金額', '自動', 'USDT 通道組 + ChannelAmount'],
            ]
        );

        if (!$this->confirm('確認執行？')) {
            $this->info('已取消。');
            return 0;
        }

        DB::beginTransaction();
        try {
            // Step 1 - 管理員
            if (!$skipAdmin) {
                $this->createAdmins($username, $password, $superUsername, $superPassword);
            } else {
                $this->info('已跳過管理員建立。');
            }

            // Step 2 - 通道
            $this->seedChannels($channels);

            // Step 3 - 銀行
            $this->seedBanks($banks);

            // Step 4 - 通道組 + ChannelAmount
            $this->seedChannelGroupAndAmount();

            // Step 5 - 測試商戶
            if (!$skipMerchant) {
                $this->createTestMerchant($merchantUsername, $merchantPassword);
            } else {
                $this->info('已跳過測試商戶建立。');
            }

            DB::commit();
            $this->newLine();
            $this->info('========== 環境初始化完成 ==========');

            $this->newLine();
            $this->info('下一步：');
            $this->line('  1. 從 Admin 建立 USDT 收款帳號（UserChannelAccount），輸入 TRON 錢包地址');
            $this->line('  2. 確保 .env 的 TRONGRID_API_KEY 已設定');
            $this->line('  3. 啟動 queue worker：php artisan queue:work');
            $this->line('  4. 啟動 deposit polling：php artisan usdt:poll-deposits');

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("初始化失敗：{$e->getMessage()}");
            return 1;
        }
    }

    private function createAdmins(string $username, string $password, string $superUsername, string $superPassword): void
    {
        $existingAdmin = User::where('role', User::ROLE_ADMIN)->first();
        if ($existingAdmin) {
            throw new \RuntimeException("已存在管理員帳號（ID: {$existingAdmin->id}, username: {$existingAdmin->username}），請使用 --skip-admin 跳過。");
        }

        if (User::where('username', $username)->exists()) {
            throw new \RuntimeException("帳號 '{$username}' 已存在。");
        }

        if (User::where('username', $superUsername)->exists()) {
            throw new \RuntimeException("帳號 '{$superUsername}' 已存在。");
        }

        // 1. 建立租戶管理員
        $tenantAdmin = $this->createAdminUser($username, $password, god: false);
        $this->info("租戶管理員建立成功（ID: {$tenantAdmin->id}）");

        // 2. 建立 wallet
        $wallet = Wallet::create([
            'user_id'                        => $tenantAdmin->id,
            'status'                         => Wallet::STATUS_ENABLE,
            'balance'                        => 0,
            'profit'                         => 0,
            'frozen_balance'                 => 0,
            'withdraw_fee'                   => 0,
            'withdraw_fee_percent'           => 0,
            'additional_withdraw_fee'        => 0,
            'withdraw_profit_fee'            => 0,
            'agency_withdraw_fee'            => 0,
            'agency_withdraw_fee_dollar'     => 3.00,
            'additional_agency_withdraw_fee' => 0,
            'withdraw_min_amount'            => 0,
            'withdraw_max_amount'            => 0,
            'agency_withdraw_min_amount'     => 0,
            'agency_withdraw_max_amount'     => 0,
        ]);

        $tenantAdmin->update(['wallet_id' => $wallet->id]);

        // 3. 建立超級管理員
        $superAdmin = $this->createAdminUser($superUsername, $superPassword, god: true);
        $superAdmin->update(['wallet_id' => $wallet->id]);
        $this->info("超級管理員建立成功（ID: {$superAdmin->id}）");

        $this->table(
            ['欄位', '租戶管理員', '超級管理員'],
            [
                ['ID', $tenantAdmin->id, $superAdmin->id],
                ['帳號', $tenantAdmin->username, $superAdmin->username],
                ['God Mode', '否', '是'],
                ['Wallet ID', $wallet->id, $wallet->id],
                ['Google 2FA', $tenantAdmin->google2fa_secret, $superAdmin->google2fa_secret],
                ['Secret Key', $tenantAdmin->secret_key, $superAdmin->secret_key],
            ]
        );

        $this->warn('請記錄 Google 2FA Secret，用於綁定 Google Authenticator。');
    }

    private function createTestMerchant(string $username, string $password): void
    {
        if (User::where('username', $username)->exists()) {
            $this->warn("商戶帳號 '{$username}' 已存在，跳過。");
            return;
        }

        $merchant = User::create([
            'role'                          => User::ROLE_MERCHANT,
            'god'                           => false,
            'status'                        => User::STATUS_ENABLE,
            'account_mode'                  => User::ACCOUNT_MODE_GENERAL,
            'name'                          => '測試商戶',
            'username'                      => $username,
            'password'                      => Hash::make($password),
            'google2fa_secret'              => $this->generateGoogle2faSecret(),
            'secret_key'                    => Str::random(32),
            'token'                         => md5(Str::random(32)),
            'wallet_id'                     => 0,
            'parent_id'                     => null,
            'agent_enable'                  => false,
            'google2fa_enable'              => false,
            'deposit_enable'                => true,
            'paufen_deposit_enable'         => true,
            'withdraw_review_enable'        => false,
            'withdraw_enable'               => true,
            'withdraw_profit_enable'        => false,
            'withdraw_google2fa_enable'     => false,
            'paufen_withdraw_enable'        => true,
            'agency_withdraw_enable'        => true,
            'paufen_agency_withdraw_enable' => true,
            'transaction_enable'            => true,
            'third_channel_enable'          => false,
            'ready_for_matching'            => false,
            'balance_transfer_enable'       => false,
            'cancel_order_enable'           => false,
            'exchange_mode_enable'          => false,
            'control_downline'              => false,
            'include_self_providers'        => false,
        ]);

        $wallet = Wallet::create([
            'user_id'                        => $merchant->id,
            'status'                         => Wallet::STATUS_ENABLE,
            'balance'                        => 10000,
            'profit'                         => 0,
            'frozen_balance'                 => 0,
            'withdraw_fee'                   => 0,
            'withdraw_fee_percent'           => 0,
            'additional_withdraw_fee'        => 0,
            'withdraw_profit_fee'            => 0,
            'agency_withdraw_fee'            => 0,
            'agency_withdraw_fee_dollar'     => 1.00,
            'additional_agency_withdraw_fee' => 0,
            'withdraw_min_amount'            => 1,
            'withdraw_max_amount'            => 50000,
            'agency_withdraw_min_amount'     => 1,
            'agency_withdraw_max_amount'     => 50000,
        ]);

        $merchant->update(['wallet_id' => $wallet->id]);

        // 建立 USDT 通道的 UserChannel
        $channelGroup = ChannelGroup::where('channel_code', 'USDT')->first();
        if ($channelGroup) {
            DB::table('user_channels')->insert([
                'user_id'          => $merchant->id,
                'channel_group_id' => $channelGroup->id,
                'fee_percent'      => '1.00',
                'status'           => 1,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            $this->info("已為商戶開通 USDT 通道。");
        }

        $this->table(
            ['欄位', '值'],
            [
                ['ID', $merchant->id],
                ['帳號', $merchant->username],
                ['餘額', '10,000.00'],
                ['Secret Key', $merchant->secret_key],
                ['Google 2FA', $merchant->google2fa_secret],
                ['Wallet ID', $wallet->id],
            ]
        );

        $this->info("測試商戶建立成功（ID: {$merchant->id}）");
    }

    private function seedChannels(array $channels): void
    {
        if (empty($channels)) {
            $this->info('[通道] 無資料，跳過。');
            return;
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($channels as $channel) {
            if (Channel::where('code', $channel['code'])->exists()) {
                $skipped++;
                continue;
            }

            Channel::create($channel);
            $inserted++;
        }

        $this->info("[通道] 新增 {$inserted} 筆，跳過 {$skipped} 筆已存在。");
    }

    private function seedBanks(array $banks): void
    {
        if (empty($banks)) {
            $this->info('[銀行] 無資料，跳過。');
            return;
        }

        $inserted = 0;
        $skipped = 0;

        foreach ($banks as $bank) {
            if (Bank::where('name', $bank['name'])->exists()) {
                $skipped++;
                continue;
            }

            Bank::create(array_merge($bank, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
            $inserted++;
        }

        $this->info("[銀行] 新增 {$inserted} 筆，跳過 {$skipped} 筆已存在。");
    }

    private function seedChannelGroupAndAmount(): void
    {
        $channel = Channel::where('code', 'USDT')->first();
        if (!$channel) {
            $this->warn('[通道組] USDT 通道不存在，跳過。');
            return;
        }

        $channelGroup = ChannelGroup::where('channel_code', 'USDT')->first();
        if ($channelGroup) {
            $this->info('[通道組] USDT 通道組已存在，跳過。');
            return;
        }

        $channelGroup = ChannelGroup::create([
            'channel_code' => 'USDT',
            'fixed_amount' => false,
        ]);

        ChannelAmount::create([
            'channel_group_id' => $channelGroup->id,
            'channel_code'     => 'USDT',
            'min_amount'       => '1.00',
            'max_amount'       => '50000.00',
        ]);

        $this->info("[通道組] 建立 USDT 通道組（ID: {$channelGroup->id}）+ ChannelAmount（1 ~ 50,000）。");
    }

    private function createAdminUser(string $username, string $password, bool $god): User
    {
        return User::create([
            'role'                          => User::ROLE_ADMIN,
            'god'                           => $god,
            'status'                        => User::STATUS_ENABLE,
            'account_mode'                  => User::ACCOUNT_MODE_GENERAL,
            'name'                          => $god ? '超級管理員' : '最高管理員',
            'username'                      => $username,
            'password'                      => Hash::make($password),
            'google2fa_secret'              => $this->generateGoogle2faSecret(),
            'secret_key'                    => Str::random(32),
            'token'                         => md5(Str::random(32)),
            'wallet_id'                     => 0,
            'parent_id'                     => null,
            'agent_enable'                  => true,
            'google2fa_enable'              => true,
            'deposit_enable'                => false,
            'paufen_deposit_enable'         => false,
            'withdraw_review_enable'        => false,
            'withdraw_enable'               => false,
            'withdraw_profit_enable'        => false,
            'withdraw_google2fa_enable'     => false,
            'paufen_withdraw_enable'        => false,
            'agency_withdraw_enable'        => false,
            'paufen_agency_withdraw_enable' => false,
            'transaction_enable'            => false,
            'third_channel_enable'          => false,
            'ready_for_matching'            => false,
            'balance_transfer_enable'       => false,
            'cancel_order_enable'           => false,
            'exchange_mode_enable'          => false,
            'control_downline'              => false,
            'include_self_providers'        => false,
        ]);
    }

    private function generateGoogle2faSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $secret;
    }
}

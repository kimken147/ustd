# Chain Transaction Sync Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Sync on-chain TRC20 USDT transactions to local database for admin viewing and reconciliation.

**Architecture:** New `chain_transactions` table stores all USDT transfers. Hybrid sync: piggyback on existing 30s deposit polling + hourly catchup command. Auto-matching links chain transactions to system `transactions` records via tx_hash or amount+time window. Admin frontend provides list page with filtering, manual match, and ignore actions.

**Tech Stack:** Laravel 11, PHP 8.2+, TronGrid API, React 18, Refine v5, Ant Design v5, TypeScript

---

### Task 1: Migration + Model

**Files:**
- Create: `api/database/migrations/2026_03_09_000001_create_chain_transactions_table.php`
- Create: `api/app/Models/ChainTransaction.php`

**Step 1: Create migration**

```php
<?php
// api/database/migrations/2026_03_09_000001_create_chain_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chain_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tx_hash', 66)->unique();
            $table->unsignedInteger('user_channel_account_id')->nullable()->index();
            $table->enum('direction', ['in', 'out']);
            $table->string('from_address', 42)->index();
            $table->string('to_address', 42)->index();
            $table->decimal('amount', 20, 6);
            $table->unsignedBigInteger('block_number')->nullable();
            $table->timestamp('block_timestamp')->index();
            $table->unsignedInteger('confirmations')->default(0);
            $table->enum('match_status', ['pending', 'matched', 'unmatched', 'ignored'])->default('pending')->index();
            $table->unsignedInteger('matched_transaction_id')->nullable()->index();
            $table->timestamp('matched_at')->nullable();
            $table->unsignedInteger('matched_by')->nullable();
            $table->text('note')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->foreign('user_channel_account_id')->references('id')->on('user_channel_accounts')->nullOnDelete();
            $table->foreign('matched_transaction_id')->references('id')->on('transactions')->nullOnDelete();
            $table->foreign('matched_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chain_transactions');
    }
};
```

**Step 2: Create model**

```php
<?php
// api/app/Models/ChainTransaction.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChainTransaction extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_MATCHED = 'matched';
    const STATUS_UNMATCHED = 'unmatched';
    const STATUS_IGNORED = 'ignored';

    const DIRECTION_IN = 'in';
    const DIRECTION_OUT = 'out';

    protected $fillable = [
        'tx_hash',
        'user_channel_account_id',
        'direction',
        'from_address',
        'to_address',
        'amount',
        'block_number',
        'block_timestamp',
        'confirmations',
        'match_status',
        'matched_transaction_id',
        'matched_at',
        'matched_by',
        'note',
        'raw_data',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
        'block_timestamp' => 'datetime',
        'matched_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function userChannelAccount()
    {
        return $this->belongsTo(UserChannelAccount::class);
    }

    public function matchedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }

    public function matchedByUser()
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function scopePending($query)
    {
        return $query->where('match_status', self::STATUS_PENDING);
    }

    public function scopeUnmatched($query)
    {
        return $query->where('match_status', self::STATUS_UNMATCHED);
    }
}
```

**Step 3: Run migration**

Run: `cd /Users/apple/projects/morgan/ustd/api && php artisan migrate`
Expected: Migration runs successfully, `chain_transactions` table created.

**Step 4: Commit**

```bash
git add api/database/migrations/2026_03_09_000001_create_chain_transactions_table.php api/app/Models/ChainTransaction.php
git commit -m "feat: add chain_transactions migration and model"
```

---

### Task 2: Extend Trc20Adapter with fetchTransactionHistory

**Files:**
- Modify: `api/app/Services/Crypto/Adapters/Trc20Adapter.php`
- Modify: `api/app/Services/Crypto/Adapters/ChainAdapterInterface.php`

**Step 1: Add `fetchTransactionHistory()` to ChainAdapterInterface**

After the existing `getTransactionInfo` method in `api/app/Services/Crypto/Adapters/ChainAdapterInterface.php`, add:

```php
/**
 * 取得指定地址的 USDT 交易歷史（含收款和付款）
 *
 * @param string $address 錢包地址
 * @param int $limit 每頁筆數 (max 200)
 * @param string|null $fingerprint 分頁游標
 * @param string|null $minTimestamp 最早時間戳 (毫秒)
 * @return array{data: Collection, fingerprint: string|null}
 */
public function fetchTransactionHistory(
    string $address,
    int $limit = 200,
    ?string $fingerprint = null,
    ?string $minTimestamp = null,
): array;
```

**Step 2: Implement in Trc20Adapter**

Add this method to `api/app/Services/Crypto/Adapters/Trc20Adapter.php` after `fetchIncomingTransactions`:

```php
public function fetchTransactionHistory(
    string $address,
    int $limit = 200,
    ?string $fingerprint = null,
    ?string $minTimestamp = null,
): array {
    try {
        $params = [
            'contract_address' => $this->getUsdtContract(),
            'limit' => min($limit, 200),
            'order_by' => 'block_timestamp,desc',
        ];

        if ($fingerprint) {
            $params['fingerprint'] = $fingerprint;
        }

        if ($minTimestamp) {
            $params['min_timestamp'] = $minTimestamp;
        }

        $response = $this->buildHttpClient()
            ->get($this->getBaseUrl() . "/v1/accounts/{$address}/transactions/trc20", $params);

        if (!$response->successful()) {
            Log::warning('Trc20Adapter: fetchTransactionHistory API 請求失敗', [
                'address' => $address,
                'status' => $response->status(),
            ]);
            return ['data' => collect(), 'fingerprint' => null];
        }

        $json = $response->json();
        $data = $json['data'] ?? [];
        $nextFingerprint = $json['meta']['fingerprint'] ?? null;

        $transactions = collect($data)
            ->filter(fn ($tx) => ($tx['token_info']['address'] ?? '') === $this->getUsdtContract())
            ->map(function ($tx) {
                $decimals = (int) ($tx['token_info']['decimals'] ?? 6);
                $rawAmount = $tx['value'] ?? '0';
                $amount = bcdiv($rawAmount, bcpow('10', (string) $decimals), 6);

                return [
                    'tx_hash' => $tx['transaction_id'],
                    'from' => $tx['from'],
                    'to' => $tx['to'],
                    'amount' => $amount,
                    'block_timestamp' => (int) ($tx['block_timestamp'] ?? 0),
                    'block_number' => (int) ($tx['block_number'] ?? 0),
                    'type' => $tx['type'] ?? 'Transfer',
                    'raw' => $tx,
                ];
            });

        return ['data' => $transactions, 'fingerprint' => $nextFingerprint];
    } catch (\Exception $e) {
        Log::error('Trc20Adapter: fetchTransactionHistory 發生例外', [
            'address' => $address,
            'exception' => $e->getMessage(),
        ]);
        return ['data' => collect(), 'fingerprint' => null];
    }
}
```

**Step 3: Commit**

```bash
git add api/app/Services/Crypto/Adapters/ChainAdapterInterface.php api/app/Services/Crypto/Adapters/Trc20Adapter.php
git commit -m "feat: add fetchTransactionHistory to chain adapter"
```

---

### Task 3: ChainTransactionSyncService

**Files:**
- Create: `api/app/Services/Crypto/ChainTransactionSyncService.php`

**Step 1: Create sync service**

```php
<?php
// api/app/Services/Crypto/ChainTransactionSyncService.php

namespace App\Services\Crypto;

use App\Models\ChainTransaction;
use App\Models\Channel;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ChainTransactionSyncService
{
    public function __construct(
        private readonly ChainTransactionMatchService $matchService,
    ) {}

    /**
     * 從 CryptoMonitorService 輪詢中順便存入鏈上交易
     * 不增加額外 API 呼叫
     */
    public function processFromPolling(Collection $chainTxDtos, UserChannelAccount $account): void
    {
        foreach ($chainTxDtos as $dto) {
            $this->upsertTransaction([
                'tx_hash' => $dto->txHash,
                'from' => $dto->from,
                'to' => $dto->to,
                'amount' => $dto->amount,
                'block_timestamp' => $dto->timestamp,
                'block_number' => null,
                'raw' => null,
            ], $account);
        }
    }

    /**
     * 同步單一帳號的最近交易（補漏排程用）
     */
    public function syncRecentTransactions(UserChannelAccount $account): int
    {
        $adapter = $this->resolveAdapter($account->chain_network ?? 'trc20');
        if (!$adapter) {
            return 0;
        }

        $address = $this->getAccountAddress($account);
        if (!$address) {
            return 0;
        }

        $result = $adapter->fetchTransactionHistory($address, 200);
        $count = 0;

        foreach ($result['data'] as $txData) {
            if ($this->upsertTransaction($txData, $account)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * 同步所有 USDT 帳號
     */
    public function syncAllAccounts(): array
    {
        $accounts = UserChannelAccount::where('channel_code', Channel::CODE_USDT)
            ->whereNull('deleted_at')
            ->get();

        $totalSynced = 0;
        $totalAccounts = $accounts->count();

        foreach ($accounts as $account) {
            try {
                $synced = $this->syncRecentTransactions($account);
                $totalSynced += $synced;
            } catch (\Exception $e) {
                Log::error('ChainTransactionSyncService: 同步帳號失敗', [
                    'account_id' => $account->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return ['synced' => $totalSynced, 'accounts' => $totalAccounts];
    }

    /**
     * 歷史回溯：拉取指定天數內的交易
     */
    public function backfillHistory(UserChannelAccount $account, int $days = 30): int
    {
        $adapter = $this->resolveAdapter($account->chain_network ?? 'trc20');
        if (!$adapter) {
            return 0;
        }

        $address = $this->getAccountAddress($account);
        if (!$address) {
            return 0;
        }

        $minTimestamp = (string) (now()->subDays($days)->timestamp * 1000);
        $fingerprint = null;
        $totalCount = 0;

        do {
            $result = $adapter->fetchTransactionHistory($address, 200, $fingerprint, $minTimestamp);

            foreach ($result['data'] as $txData) {
                if ($this->upsertTransaction($txData, $account)) {
                    $totalCount++;
                }
            }

            $fingerprint = $result['fingerprint'];

            // 防止無窮迴圈
            if ($result['data']->isEmpty()) {
                break;
            }
        } while ($fingerprint);

        return $totalCount;
    }

    /**
     * 解析並存入單筆鏈上交易，回傳是否為新記錄
     */
    private function upsertTransaction(array $txData, UserChannelAccount $account): bool
    {
        $address = $this->getAccountAddress($account);
        $toAddress = $txData['to'];
        $fromAddress = $txData['from'];

        // 判斷方向
        $direction = null;
        if (strtolower($toAddress) === strtolower($address)) {
            $direction = ChainTransaction::DIRECTION_IN;
        } elseif (strtolower($fromAddress) === strtolower($address)) {
            $direction = ChainTransaction::DIRECTION_OUT;
        } else {
            return false; // 與此帳號無關
        }

        // 轉換 block_timestamp（毫秒 → Carbon）
        $blockTimestamp = $txData['block_timestamp'];
        if (is_numeric($blockTimestamp) && $blockTimestamp > 1e12) {
            $blockTimestamp = \Carbon\Carbon::createFromTimestampMs($blockTimestamp);
        } elseif (is_numeric($blockTimestamp)) {
            $blockTimestamp = \Carbon\Carbon::createFromTimestamp($blockTimestamp);
        }

        $chainTx = ChainTransaction::updateOrCreate(
            ['tx_hash' => $txData['tx_hash']],
            [
                'user_channel_account_id' => $account->id,
                'direction' => $direction,
                'from_address' => $fromAddress,
                'to_address' => $toAddress,
                'amount' => $txData['amount'],
                'block_number' => $txData['block_number'] ?? null,
                'block_timestamp' => $blockTimestamp,
                'confirmations' => 1,
                'raw_data' => $txData['raw'] ?? null,
            ]
        );

        // 只對新建立的記錄嘗試比對
        if ($chainTx->wasRecentlyCreated && $chainTx->match_status === ChainTransaction::STATUS_PENDING) {
            $this->matchService->matchTransaction($chainTx);
        }

        return $chainTx->wasRecentlyCreated;
    }

    private function getAccountAddress(UserChannelAccount $account): ?string
    {
        $detail = $account->detail;
        if (is_string($detail)) {
            $detail = json_decode($detail, true);
        }
        return $detail['wallet_address'] ?? $detail['account'] ?? $account->account ?? null;
    }

    private function resolveAdapter(string $network): ?ChainAdapterInterface
    {
        return match ($network) {
            'trc20' => app(Trc20Adapter::class),
            default => null,
        };
    }
}
```

**Step 2: Commit**

```bash
git add api/app/Services/Crypto/ChainTransactionSyncService.php
git commit -m "feat: add ChainTransactionSyncService for chain tx sync"
```

---

### Task 4: ChainTransactionMatchService

**Files:**
- Create: `api/app/Services/Crypto/ChainTransactionMatchService.php`

**Step 1: Create match service**

```php
<?php
// api/app/Services/Crypto/ChainTransactionMatchService.php

namespace App\Services\Crypto;

use App\Models\ChainTransaction;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class ChainTransactionMatchService
{
    /**
     * 嘗試自動比對單筆鏈上交易
     */
    public function matchTransaction(ChainTransaction $chainTx): bool
    {
        if ($chainTx->match_status !== ChainTransaction::STATUS_PENDING) {
            return false;
        }

        // 策略 1: tx_hash 精確匹配
        $matched = Transaction::where('tx_hash', $chainTx->tx_hash)->first();
        if ($matched) {
            $this->markMatched($chainTx, $matched->id);
            return true;
        }

        // 策略 2: 金額 + 時間窗口匹配（僅收款）
        if ($chainTx->direction === ChainTransaction::DIRECTION_IN && $chainTx->user_channel_account_id) {
            $candidates = Transaction::where('channel_code', 'USDT')
                ->where(function ($q) use ($chainTx) {
                    $q->where('from_channel_account_id', $chainTx->user_channel_account_id)
                        ->orWhere('to_channel_account_id', $chainTx->user_channel_account_id);
                })
                ->whereRaw('ABS(amount - ?) < 0.000001', [$chainTx->amount])
                ->whereBetween('created_at', [
                    $chainTx->block_timestamp->copy()->subMinutes(10),
                    $chainTx->block_timestamp->copy()->addMinutes(10),
                ])
                ->whereNull('tx_hash') // 尚未有 tx_hash 的才需要匹配
                ->get();

            if ($candidates->count() === 1) {
                $this->markMatched($chainTx, $candidates->first()->id);
                return true;
            }
        }

        return false;
    }

    /**
     * 批次比對所有 pending 記錄
     */
    public function matchPendingTransactions(): int
    {
        $pending = ChainTransaction::pending()->get();
        $matched = 0;

        foreach ($pending as $chainTx) {
            if ($this->matchTransaction($chainTx)) {
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * 管理員手動關聯
     */
    public function manualMatch(ChainTransaction $chainTx, int $transactionId, int $userId): void
    {
        $chainTx->update([
            'match_status' => ChainTransaction::STATUS_MATCHED,
            'matched_transaction_id' => $transactionId,
            'matched_at' => now(),
            'matched_by' => $userId,
        ]);

        Log::info('ChainTransaction: 手動關聯', [
            'chain_tx_id' => $chainTx->id,
            'transaction_id' => $transactionId,
            'matched_by' => $userId,
        ]);
    }

    /**
     * 將超過指定時間的 pending 標記為 unmatched
     */
    public function markStaleAsUnmatched(int $hours = 24): int
    {
        return ChainTransaction::where('match_status', ChainTransaction::STATUS_PENDING)
            ->where('created_at', '<', now()->subHours($hours))
            ->update(['match_status' => ChainTransaction::STATUS_UNMATCHED]);
    }

    private function markMatched(ChainTransaction $chainTx, int $transactionId): void
    {
        $chainTx->update([
            'match_status' => ChainTransaction::STATUS_MATCHED,
            'matched_transaction_id' => $transactionId,
            'matched_at' => now(),
            'matched_by' => null, // null = 自動比對
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add api/app/Services/Crypto/ChainTransactionMatchService.php
git commit -m "feat: add ChainTransactionMatchService for auto/manual matching"
```

---

### Task 5: Modify CryptoMonitorService to piggyback sync

**Files:**
- Modify: `api/app/Services/Crypto/CryptoMonitorService.php`

**Step 1: Inject ChainTransactionSyncService and call processFromPolling**

In the constructor, add `ChainTransactionSyncService`:

```php
public function __construct(
    private readonly TransactionStatusService $transactionStatusService,
    private readonly ChainTransactionSyncService $chainTransactionSyncService,
) {}
```

In the `pollAddress` method, after `$chainTxs = $adapter->fetchIncomingTransactions($address);` and before the empty check, add the sync call. The first monitor in `$monitors` has the `userChannelAccount` relation loaded:

```php
private function pollAddress(ChainAdapterInterface $adapter, string $address, $monitors): void
{
    $chainTxs = $adapter->fetchIncomingTransactions($address);

    // 順便同步鏈上交易到 chain_transactions 表
    $account = $monitors->first()->userChannelAccount;
    if ($account && $chainTxs->isNotEmpty()) {
        try {
            $this->chainTransactionSyncService->processFromPolling($chainTxs, $account);
        } catch (\Exception $e) {
            Log::warning('CryptoMonitorService: 同步鏈上交易失敗', [
                'address' => $address,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    // ... rest of existing code unchanged
```

**Step 2: Commit**

```bash
git add api/app/Services/Crypto/CryptoMonitorService.php
git commit -m "feat: piggyback chain tx sync on existing deposit polling"
```

---

### Task 6: Artisan Commands

**Files:**
- Create: `api/app/Console/Commands/SyncChainTransactions.php`
- Create: `api/app/Console/Commands/BackfillChainHistory.php`
- Create: `api/app/Console/Commands/MarkUnmatchedChainTransactions.php`

**Step 1: Create hourly sync command**

```php
<?php
// api/app/Console/Commands/SyncChainTransactions.php

namespace App\Console\Commands;

use App\Services\Crypto\ChainTransactionSyncService;
use App\Services\Crypto\ChainTransactionMatchService;
use Illuminate\Console\Command;

class SyncChainTransactions extends Command
{
    protected $signature = 'chain:sync-transactions';
    protected $description = '同步所有 USDT 帳號的鏈上交易（補漏）';

    public function handle(
        ChainTransactionSyncService $syncService,
        ChainTransactionMatchService $matchService,
    ): int {
        $this->info('開始同步鏈上交易...');

        $result = $syncService->syncAllAccounts();
        $this->info("同步完成：{$result['accounts']} 個帳號，新增 {$result['synced']} 筆交易");

        // 補漏排程結束後重新比對 pending 記錄
        $matched = $matchService->matchPendingTransactions();
        $this->info("重新比對完成：{$matched} 筆匹配");

        return self::SUCCESS;
    }
}
```

**Step 2: Create backfill command**

```php
<?php
// api/app/Console/Commands/BackfillChainHistory.php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\UserChannelAccount;
use App\Services\Crypto\ChainTransactionSyncService;
use Illuminate\Console\Command;

class BackfillChainHistory extends Command
{
    protected $signature = 'chain:backfill-history {--days=30} {--account_id=}';
    protected $description = '回溯拉取鏈上交易歷史';

    public function handle(ChainTransactionSyncService $syncService): int
    {
        $days = (int) $this->option('days');
        $accountId = $this->option('account_id');

        if ($accountId) {
            $accounts = UserChannelAccount::where('id', $accountId)
                ->where('channel_code', Channel::CODE_USDT)
                ->get();
        } else {
            $accounts = UserChannelAccount::where('channel_code', Channel::CODE_USDT)
                ->whereNull('deleted_at')
                ->get();
        }

        if ($accounts->isEmpty()) {
            $this->warn('沒有找到符合條件的 USDT 帳號');
            return self::SUCCESS;
        }

        $this->info("開始回溯 {$days} 天的歷史交易，共 {$accounts->count()} 個帳號...");
        $bar = $this->output->createProgressBar($accounts->count());

        $totalCount = 0;
        foreach ($accounts as $account) {
            try {
                $count = $syncService->backfillHistory($account, $days);
                $totalCount += $count;
                $this->line(" 帳號 {$account->id}: 新增 {$count} 筆");
            } catch (\Exception $e) {
                $this->error(" 帳號 {$account->id} 失敗: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("回溯完成：共新增 {$totalCount} 筆交易");

        return self::SUCCESS;
    }
}
```

**Step 3: Create mark-unmatched command**

```php
<?php
// api/app/Console/Commands/MarkUnmatchedChainTransactions.php

namespace App\Console\Commands;

use App\Services\Crypto\ChainTransactionMatchService;
use Illuminate\Console\Command;

class MarkUnmatchedChainTransactions extends Command
{
    protected $signature = 'chain:mark-unmatched {--hours=24}';
    protected $description = '將超過指定時間的 pending 鏈上交易標記為 unmatched';

    public function handle(ChainTransactionMatchService $matchService): int
    {
        $hours = (int) $this->option('hours');
        $count = $matchService->markStaleAsUnmatched($hours);
        $this->info("已將 {$count} 筆超過 {$hours} 小時的交易標記為 unmatched");

        return self::SUCCESS;
    }
}
```

**Step 4: Register commands in scheduler**

Check: `api/app/Console/Kernel.php` — Laravel 11 may auto-discover commands. If using `routes/console.php` or `Kernel.php`, register the schedule:

```php
// In the schedule method (Kernel.php or routes/console.php):
$schedule->command('chain:sync-transactions')->hourly();
$schedule->command('chain:mark-unmatched')->daily();
```

**Step 5: Commit**

```bash
git add api/app/Console/Commands/SyncChainTransactions.php api/app/Console/Commands/BackfillChainHistory.php api/app/Console/Commands/MarkUnmatchedChainTransactions.php
git commit -m "feat: add artisan commands for chain tx sync, backfill, and mark-unmatched"
```

---

### Task 7: Permission + API Resource

**Files:**
- Modify: `api/app/Models/Permission.php` — Add new constant
- Modify: `api/database/seeds/PermissionSeeder.php` — Add new permission entries
- Create: `api/app/Http/Resources/ChainTransactionResource.php`

**Step 1: Add permission constants**

In `api/app/Models/Permission.php`, add after `ADMIN_INTERNAL_TRANSFER = 35`:

```php
const ADMIN_VIEW_CHAIN_TRANSACTION = 36;
const ADMIN_UPDATE_CHAIN_TRANSACTION = 37;
```

**Step 2: Add to seeder**

In `api/database/seeds/PermissionSeeder.php`, add a new group constant and two entries:

```php
const GROUP_NAME_CHAIN_TRANSACTION = '鏈上交易管理';
```

And in the `$permissions` array, add:

```php
Permission::ADMIN_VIEW_CHAIN_TRANSACTION => [
    'role'       => User::ROLE_ADMIN,
    'group_name' => self::GROUP_NAME_CHAIN_TRANSACTION,
    'name'       => '查看鏈上交易',
],
Permission::ADMIN_UPDATE_CHAIN_TRANSACTION => [
    'role'       => User::ROLE_ADMIN,
    'group_name' => self::GROUP_NAME_CHAIN_TRANSACTION,
    'name'       => '管理鏈上交易',
],
```

**Step 3: Create API Resource**

```php
<?php
// api/app/Http/Resources/ChainTransactionResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChainTransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tx_hash' => $this->tx_hash,
            'user_channel_account_id' => $this->user_channel_account_id,
            'user_channel_account' => $this->whenLoaded('userChannelAccount', fn () => [
                'id' => $this->userChannelAccount->id,
                'name' => $this->userChannelAccount->name,
                'account' => $this->userChannelAccount->account,
            ]),
            'direction' => $this->direction,
            'from_address' => $this->from_address,
            'to_address' => $this->to_address,
            'amount' => $this->amount,
            'block_number' => $this->block_number,
            'block_timestamp' => $this->block_timestamp?->toISOString(),
            'confirmations' => $this->confirmations,
            'match_status' => $this->match_status,
            'matched_transaction_id' => $this->matched_transaction_id,
            'matched_transaction' => $this->whenLoaded('matchedTransaction', fn () => [
                'id' => $this->matchedTransaction->id,
                'order_number' => $this->matchedTransaction->order_number,
                'amount' => $this->matchedTransaction->amount,
                'status' => $this->matchedTransaction->status,
            ]),
            'matched_at' => $this->matched_at?->toISOString(),
            'matched_by' => $this->matched_by,
            'matched_by_user' => $this->whenLoaded('matchedByUser', fn () => [
                'id' => $this->matchedByUser->id,
                'name' => $this->matchedByUser->name,
            ]),
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
```

**Step 4: Commit**

```bash
git add api/app/Models/Permission.php api/database/seeds/PermissionSeeder.php api/app/Http/Resources/ChainTransactionResource.php
git commit -m "feat: add chain transaction permission and API resource"
```

---

### Task 8: Admin Controller + Routes

**Files:**
- Create: `api/app/Http/Controllers/Admin/ChainTransactionController.php`
- Modify: `api/routes/api-v1.php`

**Step 1: Create controller**

```php
<?php
// api/app/Http/Controllers/Admin/ChainTransactionController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChainTransactionResource;
use App\Models\ChainTransaction;
use App\Models\Permission;
use App\Models\Transaction;
use App\Services\Crypto\ChainTransactionMatchService;
use App\Services\Crypto\ChainTransactionSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChainTransactionController extends Controller
{
    public function __construct()
    {
        $this->middleware([
            'permission:' . Permission::ADMIN_VIEW_CHAIN_TRANSACTION,
        ])->only(['index', 'show']);
        $this->middleware([
            'permission:' . Permission::ADMIN_UPDATE_CHAIN_TRANSACTION,
        ])->only(['match', 'ignore', 'restore', 'sync']);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ChainTransaction::with(['userChannelAccount', 'matchedTransaction'])
            ->orderByDesc('block_timestamp');

        if ($request->filled('match_status')) {
            $query->where('match_status', $request->input('match_status'));
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->input('direction'));
        }

        if ($request->filled('user_channel_account_id')) {
            $query->where('user_channel_account_id', $request->input('user_channel_account_id'));
        }

        if ($request->filled('tx_hash')) {
            $query->where('tx_hash', $request->input('tx_hash'));
        }

        if ($request->filled('address')) {
            $address = $request->input('address');
            $query->where(function ($q) use ($address) {
                $q->where('from_address', $address)
                    ->orWhere('to_address', $address);
            });
        }

        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', $request->input('amount_min'));
        }

        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', $request->input('amount_max'));
        }

        if ($request->filled('start_date')) {
            $query->where('block_timestamp', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->where('block_timestamp', '<=', $request->input('end_date'));
        }

        $perPage = $request->input('per_page', 20);
        $data = $query->paginate($perPage);

        return ChainTransactionResource::collection($data);
    }

    public function show(ChainTransaction $chainTransaction): ChainTransactionResource
    {
        $chainTransaction->load(['userChannelAccount', 'matchedTransaction', 'matchedByUser']);
        return new ChainTransactionResource($chainTransaction);
    }

    public function match(
        Request $request,
        ChainTransaction $chainTransaction,
        ChainTransactionMatchService $matchService,
    ) {
        $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
        ]);

        if ($chainTransaction->match_status === ChainTransaction::STATUS_MATCHED) {
            return response()->json(['message' => '此交易已經被關聯'], 422);
        }

        $matchService->manualMatch(
            $chainTransaction,
            $request->input('transaction_id'),
            auth()->id(),
        );

        return new ChainTransactionResource($chainTransaction->fresh(['userChannelAccount', 'matchedTransaction']));
    }

    public function ignore(Request $request, ChainTransaction $chainTransaction)
    {
        if ($chainTransaction->match_status === ChainTransaction::STATUS_MATCHED) {
            return response()->json(['message' => '已匹配的交易不能忽略'], 422);
        }

        $chainTransaction->update([
            'match_status' => ChainTransaction::STATUS_IGNORED,
            'note' => $request->input('note', $chainTransaction->note),
        ]);

        return new ChainTransactionResource($chainTransaction->fresh());
    }

    public function restore(ChainTransaction $chainTransaction, ChainTransactionMatchService $matchService)
    {
        if ($chainTransaction->match_status !== ChainTransaction::STATUS_IGNORED) {
            return response()->json(['message' => '只能恢復被忽略的交易'], 422);
        }

        $chainTransaction->update([
            'match_status' => ChainTransaction::STATUS_PENDING,
            'matched_transaction_id' => null,
            'matched_at' => null,
            'matched_by' => null,
        ]);

        // 恢復後嘗試自動比對
        $matchService->matchTransaction($chainTransaction->fresh());

        return new ChainTransactionResource($chainTransaction->fresh());
    }

    public function sync(ChainTransactionSyncService $syncService)
    {
        $result = $syncService->syncAllAccounts();
        return response()->json([
            'message' => "同步完成：{$result['accounts']} 個帳號，新增 {$result['synced']} 筆交易",
            'data' => $result,
        ]);
    }
}
```

**Step 2: Add routes**

In `api/routes/api-v1.php`, inside the admin authenticated middleware group (after the `tags` resource around line 353), add:

```php
Route::apiResource(
    'chain-transactions',
    'ChainTransactionController'
)->only(['index', 'show']);
Route::put(
    'chain-transactions/{chainTransaction}/match',
    'ChainTransactionController@match'
);
Route::put(
    'chain-transactions/{chainTransaction}/ignore',
    'ChainTransactionController@ignore'
);
Route::put(
    'chain-transactions/{chainTransaction}/restore',
    'ChainTransactionController@restore'
);
Route::post(
    'chain-transactions/sync',
    'ChainTransactionController@sync'
);
```

**Step 3: Commit**

```bash
git add api/app/Http/Controllers/Admin/ChainTransactionController.php api/routes/api-v1.php
git commit -m "feat: add chain transaction admin controller and routes"
```

---

### Task 9: Frontend - Shared Interface + Resource

**Files:**
- Create: `packages/shared/src/interfaces/chainTransaction.ts`
- Modify: `packages/shared/src/interfaces/index.ts`
- Modify: `packages/shared/src/lib/resouce.ts`

**Step 1: Create TypeScript interface**

```typescript
// packages/shared/src/interfaces/chainTransaction.ts

export interface ChainTransactionAccount {
  id: number;
  name: string;
  account: string;
}

export interface ChainTransactionMatchedTx {
  id: number;
  order_number: string;
  amount: string;
  status: number;
}

export interface ChainTransactionMatchedByUser {
  id: number;
  name: string;
}

export interface ChainTransaction {
  id: number;
  tx_hash: string;
  user_channel_account_id: number | null;
  user_channel_account: ChainTransactionAccount | null;
  direction: 'in' | 'out';
  from_address: string;
  to_address: string;
  amount: string;
  block_number: number | null;
  block_timestamp: string;
  confirmations: number;
  match_status: 'pending' | 'matched' | 'unmatched' | 'ignored';
  matched_transaction_id: number | null;
  matched_transaction: ChainTransactionMatchedTx | null;
  matched_at: string | null;
  matched_by: number | null;
  matched_by_user: ChainTransactionMatchedByUser | null;
  note: string | null;
  created_at: string;
  updated_at: string;
}
```

**Step 2: Export from interfaces/index.ts**

Add to `packages/shared/src/interfaces/index.ts`:

```typescript
// ChainTransaction types
export type {
    ChainTransaction,
    ChainTransactionAccount,
    ChainTransactionMatchedTx,
    ChainTransactionMatchedByUser,
} from './chainTransaction';
```

**Step 3: Add resource constant**

In `packages/shared/src/lib/resouce.ts`, add:

```typescript
chainTransactions: "chain-transactions",
```

**Step 4: Commit**

```bash
git add packages/shared/src/interfaces/chainTransaction.ts packages/shared/src/interfaces/index.ts packages/shared/src/lib/resouce.ts
git commit -m "feat: add ChainTransaction interface and resource constant"
```

---

### Task 10: Frontend - i18n Translation Files

**Files:**
- Create: `apps/admin/public/locales/zh-CN/chainTransaction.json`
- Create: `apps/admin/public/locales/en/chainTransaction.json`
- Modify: `apps/admin/public/locales/zh-CN/common.json` — Add navigation entry
- Modify: `apps/admin/public/locales/en/common.json` — Add navigation entry

**Step 1: Create zh-CN translations**

```json
{
  "titles": {
    "list": "链上交易明细",
    "show": "链上交易详情",
    "pageTitle": "链上交易管理"
  },
  "fields": {
    "txHash": "交易哈希",
    "direction": "方向",
    "fromAddress": "发送地址",
    "toAddress": "接收地址",
    "amount": "金额",
    "blockNumber": "区块号",
    "blockTimestamp": "交易时间",
    "matchStatus": "比对状态",
    "matchedTransaction": "关联订单",
    "matchedAt": "匹配时间",
    "matchedBy": "操作人",
    "userChannelAccount": "所属帐号",
    "note": "备注",
    "createdAt": "建立时间"
  },
  "direction": {
    "in": "收款",
    "out": "付款"
  },
  "matchStatus": {
    "pending": "待比对",
    "matched": "已匹配",
    "unmatched": "未匹配",
    "ignored": "已忽略"
  },
  "actions": {
    "match": "关联",
    "ignore": "忽略",
    "restore": "恢复",
    "viewTransaction": "查看订单",
    "sync": "手动同步",
    "ok": "确定",
    "cancel": "取消"
  },
  "confirmation": {
    "ignore": "确定要忽略此交易吗？",
    "restore": "确定要恢复此交易吗？",
    "sync": "确定要手动同步所有帐号的链上交易吗？"
  },
  "messages": {
    "matchSuccess": "关联成功",
    "ignoreSuccess": "已标记为忽略",
    "restoreSuccess": "已恢复",
    "syncSuccess": "同步完成"
  },
  "placeholders": {
    "selectTransaction": "搜索并选择订单",
    "txHash": "输入交易哈希",
    "address": "输入地址"
  },
  "filters": {
    "matchStatus": "比对状态",
    "direction": "方向",
    "txHash": "交易哈希",
    "address": "地址",
    "amountRange": "金额范围",
    "dateRange": "时间范围"
  }
}
```

**Step 2: Create en translations**

```json
{
  "titles": {
    "list": "On-Chain Transactions",
    "show": "Transaction Details",
    "pageTitle": "On-Chain Transaction Management"
  },
  "fields": {
    "txHash": "TX Hash",
    "direction": "Direction",
    "fromAddress": "From Address",
    "toAddress": "To Address",
    "amount": "Amount",
    "blockNumber": "Block Number",
    "blockTimestamp": "Transaction Time",
    "matchStatus": "Match Status",
    "matchedTransaction": "Matched Order",
    "matchedAt": "Matched At",
    "matchedBy": "Matched By",
    "userChannelAccount": "Account",
    "note": "Note",
    "createdAt": "Created At"
  },
  "direction": {
    "in": "Money-In",
    "out": "Money-Out"
  },
  "matchStatus": {
    "pending": "Pending",
    "matched": "Matched",
    "unmatched": "Unmatched",
    "ignored": "Ignored"
  },
  "actions": {
    "match": "Match",
    "ignore": "Ignore",
    "restore": "Restore",
    "viewTransaction": "View Order",
    "sync": "Manual Sync",
    "ok": "Confirm",
    "cancel": "Cancel"
  },
  "confirmation": {
    "ignore": "Are you sure you want to ignore this transaction?",
    "restore": "Are you sure you want to restore this transaction?",
    "sync": "Are you sure you want to sync all accounts?"
  },
  "messages": {
    "matchSuccess": "Match Successful",
    "ignoreSuccess": "Marked as Ignored",
    "restoreSuccess": "Restored",
    "syncSuccess": "Sync Complete"
  },
  "placeholders": {
    "selectTransaction": "Search and select an order",
    "txHash": "Enter TX Hash",
    "address": "Enter address"
  },
  "filters": {
    "matchStatus": "Match Status",
    "direction": "Direction",
    "txHash": "TX Hash",
    "address": "Address",
    "amountRange": "Amount Range",
    "dateRange": "Date Range"
  }
}
```

**Step 3: Add navigation entries**

In `apps/admin/public/locales/zh-CN/common.json`, add to the `navigation` object:

```json
"chainTransactions": "链上交易"
```

In `apps/admin/public/locales/en/common.json`, add to the `navigation` object:

```json
"chainTransactions": "On-Chain Transactions"
```

**Step 4: Commit**

```bash
git add apps/admin/public/locales/zh-CN/chainTransaction.json apps/admin/public/locales/en/chainTransaction.json apps/admin/public/locales/zh-CN/common.json apps/admin/public/locales/en/common.json
git commit -m "feat: add chain transaction i18n translations"
```

---

### Task 11: Frontend - List Page

**Files:**
- Create: `apps/admin/src/pages/chainTransaction/list.tsx`
- Create: `apps/admin/src/pages/chainTransaction/components/MatchModal.tsx`

**Step 1: Create MatchModal component**

```tsx
// apps/admin/src/pages/chainTransaction/components/MatchModal.tsx

import { FC, useState } from 'react';
import { Input, Modal, Table } from 'antd';
import { useApiUrl, useCustom } from '@refinedev/core';
import { useTranslation } from 'react-i18next';
import numeral from 'numeral';

interface MatchModalProps {
  open: boolean;
  chainTransactionId: number | null;
  onMatch: (transactionId: number) => void;
  onCancel: () => void;
}

export const MatchModal: FC<MatchModalProps> = ({ open, chainTransactionId, onMatch, onCancel }) => {
  const { t } = useTranslation('chainTransaction');
  const apiUrl = useApiUrl();
  const [search, setSearch] = useState('');
  const [selectedId, setSelectedId] = useState<number | null>(null);

  const { data, isLoading } = useCustom({
    url: `${apiUrl}/transactions`,
    method: 'get',
    config: {
      query: {
        order_number: search || undefined,
        per_page: 10,
        channel_code: 'USDT',
      },
    },
    queryOptions: {
      enabled: open && search.length > 0,
    },
  });

  const transactions = (data?.data as any)?.data ?? [];

  return (
    <Modal
      title={t('actions.match')}
      open={open}
      onOk={() => selectedId && onMatch(selectedId)}
      onCancel={onCancel}
      okButtonProps={{ disabled: !selectedId }}
      okText={t('actions.ok')}
      cancelText={t('actions.cancel')}
      width={700}
    >
      <Input.Search
        placeholder={t('placeholders.selectTransaction')}
        onSearch={setSearch}
        allowClear
        style={{ marginBottom: 16 }}
      />
      <Table
        loading={isLoading}
        dataSource={transactions}
        rowKey="id"
        size="small"
        pagination={false}
        rowSelection={{
          type: 'radio',
          selectedRowKeys: selectedId ? [selectedId] : [],
          onChange: (keys) => setSelectedId(keys[0] as number),
        }}
        columns={[
          { title: 'ID', dataIndex: 'id', width: 60 },
          { title: '订单号', dataIndex: 'order_number' },
          { title: '金额', dataIndex: 'amount', render: (v: string) => numeral(v).format('0,0.00') },
          { title: '状态', dataIndex: 'status' },
          { title: 'TX Hash', dataIndex: 'tx_hash', ellipsis: true },
        ]}
      />
    </Modal>
  );
};
```

**Step 2: Create list page**

```tsx
// apps/admin/src/pages/chainTransaction/list.tsx

import { FC, useCallback, useState } from 'react';
import { Button, Col, DatePicker, Input, InputNumber, Modal, Select, Space, Tag, Tooltip } from 'antd';
import { List, TextField, useTable } from '@refinedev/antd';
import { useApiUrl, useCan, useCustomMutation } from '@refinedev/core';
import { Helmet } from 'react-helmet';
import { useTranslation } from 'react-i18next';
import { SyncOutlined, LinkOutlined, EyeInvisibleOutlined, UndoOutlined, EyeOutlined } from '@ant-design/icons';
import numeral from 'numeral';
import dayjs from 'dayjs';
import { ChainTransaction, Resource, ListPageLayout, formValuesToCrudFilters } from '@morgan-ustd/shared';
import { MatchModal } from './components/MatchModal';

const { RangePicker } = DatePicker;

const TRONSCAN_BASE = 'https://tronscan.org/#/transaction/';

const matchStatusColors: Record<string, string> = {
  pending: 'processing',
  matched: 'success',
  unmatched: 'warning',
  ignored: 'default',
};

const ChainTransactionList: FC = () => {
  const { t } = useTranslation('chainTransaction');
  const apiUrl = useApiUrl();
  const { data: canEdit } = useCan({ action: '37', resource: 'chain-transactions' });

  const [matchModalOpen, setMatchModalOpen] = useState(false);
  const [matchingId, setMatchingId] = useState<number | null>(null);

  const {
    tableProps,
    searchFormProps,
    tableQuery: { refetch },
  } = useTable<ChainTransaction>({
    resource: Resource.chainTransactions,
    onSearch: formValuesToCrudFilters,
    syncWithLocation: true,
  });

  const { mutate: customMutate, isLoading: isSyncing } = useCustomMutation();

  const handleSync = useCallback(() => {
    Modal.confirm({
      title: t('confirmation.sync'),
      okText: t('actions.ok'),
      cancelText: t('actions.cancel'),
      onOk: () => {
        customMutate({
          url: `${apiUrl}/chain-transactions/sync`,
          method: 'post',
          values: {},
          successNotification: { message: t('messages.syncSuccess'), type: 'success' },
        }, {
          onSuccess: () => refetch(),
        });
      },
    });
  }, [apiUrl, customMutate, refetch, t]);

  const handleIgnore = useCallback((id: number) => {
    Modal.confirm({
      title: t('confirmation.ignore'),
      okText: t('actions.ok'),
      cancelText: t('actions.cancel'),
      onOk: () => {
        customMutate({
          url: `${apiUrl}/chain-transactions/${id}/ignore`,
          method: 'put',
          values: {},
          successNotification: { message: t('messages.ignoreSuccess'), type: 'success' },
        }, {
          onSuccess: () => refetch(),
        });
      },
    });
  }, [apiUrl, customMutate, refetch, t]);

  const handleRestore = useCallback((id: number) => {
    Modal.confirm({
      title: t('confirmation.restore'),
      okText: t('actions.ok'),
      cancelText: t('actions.cancel'),
      onOk: () => {
        customMutate({
          url: `${apiUrl}/chain-transactions/${id}/restore`,
          method: 'put',
          values: {},
          successNotification: { message: t('messages.restoreSuccess'), type: 'success' },
        }, {
          onSuccess: () => refetch(),
        });
      },
    });
  }, [apiUrl, customMutate, refetch, t]);

  const handleMatch = useCallback((transactionId: number) => {
    if (!matchingId) return;
    customMutate({
      url: `${apiUrl}/chain-transactions/${matchingId}/match`,
      method: 'put',
      values: { transaction_id: transactionId },
      successNotification: { message: t('messages.matchSuccess'), type: 'success' },
    }, {
      onSuccess: () => {
        setMatchModalOpen(false);
        setMatchingId(null);
        refetch();
      },
    });
  }, [apiUrl, customMutate, matchingId, refetch, t]);

  const columns = [
    {
      title: t('fields.blockTimestamp'),
      dataIndex: 'block_timestamp',
      width: 160,
      render: (v: string) => v ? dayjs(v).format('YYYY-MM-DD HH:mm:ss') : '-',
    },
    {
      title: t('fields.txHash'),
      dataIndex: 'tx_hash',
      width: 160,
      ellipsis: true,
      render: (v: string) => (
        <Tooltip title={v}>
          <a href={`${TRONSCAN_BASE}${v}`} target="_blank" rel="noopener noreferrer">
            {v?.slice(0, 8)}...{v?.slice(-6)}
          </a>
        </Tooltip>
      ),
    },
    {
      title: t('fields.direction'),
      dataIndex: 'direction',
      width: 80,
      render: (v: string) => (
        <Tag color={v === 'in' ? 'green' : 'blue'}>
          {t(`direction.${v}`)}
        </Tag>
      ),
    },
    {
      title: t('fields.amount'),
      dataIndex: 'amount',
      width: 120,
      render: (v: string) => <TextField value={numeral(v).format('0,0.000000')} />,
    },
    {
      title: t('fields.fromAddress'),
      dataIndex: 'from_address',
      width: 140,
      ellipsis: true,
      render: (v: string) => <Tooltip title={v}>{v?.slice(0, 6)}...{v?.slice(-4)}</Tooltip>,
    },
    {
      title: t('fields.toAddress'),
      dataIndex: 'to_address',
      width: 140,
      ellipsis: true,
      render: (v: string) => <Tooltip title={v}>{v?.slice(0, 6)}...{v?.slice(-4)}</Tooltip>,
    },
    {
      title: t('fields.userChannelAccount'),
      dataIndex: 'user_channel_account',
      width: 120,
      render: (_: any, record: ChainTransaction) =>
        record.user_channel_account
          ? `${record.user_channel_account.name}`
          : '-',
    },
    {
      title: t('fields.matchStatus'),
      dataIndex: 'match_status',
      width: 100,
      render: (v: string) => (
        <Tag color={matchStatusColors[v]}>
          {t(`matchStatus.${v}`)}
        </Tag>
      ),
    },
    {
      title: t('fields.matchedTransaction'),
      dataIndex: 'matched_transaction',
      width: 140,
      render: (_: any, record: ChainTransaction) =>
        record.matched_transaction
          ? record.matched_transaction.order_number
          : '-',
    },
    {
      title: t('actions.match'),
      width: 150,
      render: (_: any, record: ChainTransaction) => {
        const canOperate = canEdit?.can ?? false;

        if (record.match_status === 'matched') {
          return (
            <Button
              size="small"
              icon={<EyeOutlined />}
              onClick={() => window.open(`/transactions/${record.matched_transaction_id}`, '_blank')}
            >
              {t('actions.viewTransaction')}
            </Button>
          );
        }

        if (record.match_status === 'ignored') {
          return (
            <Button
              size="small"
              disabled={!canOperate}
              icon={<UndoOutlined />}
              onClick={() => handleRestore(record.id)}
            >
              {t('actions.restore')}
            </Button>
          );
        }

        return (
          <Space>
            <Button
              size="small"
              disabled={!canOperate}
              icon={<LinkOutlined />}
              onClick={() => {
                setMatchingId(record.id);
                setMatchModalOpen(true);
              }}
            >
              {t('actions.match')}
            </Button>
            <Button
              size="small"
              disabled={!canOperate}
              icon={<EyeInvisibleOutlined />}
              onClick={() => handleIgnore(record.id)}
            >
              {t('actions.ignore')}
            </Button>
          </Space>
        );
      },
    },
  ];

  return (
    <>
      <Helmet>
        <title>{t('titles.pageTitle')}</title>
      </Helmet>
      <List
        headerButtons={() => (
          <Button
            icon={<SyncOutlined spin={isSyncing} />}
            onClick={handleSync}
            disabled={!(canEdit?.can ?? false)}
          >
            {t('actions.sync')}
          </Button>
        )}
      >
        <ListPageLayout>
          <ListPageLayout.Filter formProps={searchFormProps}>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('filters.matchStatus')} name="match_status">
                <Select
                  allowClear
                  options={[
                    { value: 'pending', label: t('matchStatus.pending') },
                    { value: 'matched', label: t('matchStatus.matched') },
                    { value: 'unmatched', label: t('matchStatus.unmatched') },
                    { value: 'ignored', label: t('matchStatus.ignored') },
                  ]}
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('filters.direction')} name="direction">
                <Select
                  allowClear
                  options={[
                    { value: 'in', label: t('direction.in') },
                    { value: 'out', label: t('direction.out') },
                  ]}
                />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('filters.txHash')} name="tx_hash">
                <Input allowClear placeholder={t('placeholders.txHash')} />
              </ListPageLayout.Filter.Item>
            </Col>
            <Col xs={24} md={6}>
              <ListPageLayout.Filter.Item label={t('filters.address')} name="address">
                <Input allowClear placeholder={t('placeholders.address')} />
              </ListPageLayout.Filter.Item>
            </Col>
          </ListPageLayout.Filter>
        </ListPageLayout>

        <ListPageLayout.Table
          {...tableProps}
          columns={columns}
          scroll={{ x: 1400 }}
        />
      </List>

      <MatchModal
        open={matchModalOpen}
        chainTransactionId={matchingId}
        onMatch={handleMatch}
        onCancel={() => {
          setMatchModalOpen(false);
          setMatchingId(null);
        }}
      />
    </>
  );
};

export default ChainTransactionList;
```

**Step 3: Commit**

```bash
git add apps/admin/src/pages/chainTransaction/list.tsx apps/admin/src/pages/chainTransaction/components/MatchModal.tsx
git commit -m "feat: add chain transaction list page with match modal"
```

---

### Task 12: Frontend - App.tsx Routes

**Files:**
- Modify: `apps/admin/src/App.tsx`

**Step 1: Add import**

After the existing page imports (around line 71), add:

```typescript
import ChainTransactionList from 'pages/chainTransaction/list';
```

**Step 2: Add resource definition**

In the `resources` array (around line 131), add after the `user-channel-accounts` resource entry:

```typescript
{
  name: 'chain-transactions',
  list: '/chain-transactions',
  meta: { label: t('navigation.chainTransactions'), icon: <SwapOutlined /> },
},
```

Note: `SwapOutlined` is already imported.

**Step 3: Add route**

In the Routes section (around line 391), after the user-channel-accounts routes, add:

```tsx
{/* Chain Transactions */}
<Route path="/chain-transactions" element={<ChainTransactionList />} />
```

**Step 4: Commit**

```bash
git add apps/admin/src/App.tsx
git commit -m "feat: add chain transaction routes to admin app"
```

---

### Task 13: Register Schedule + Seed Permissions in DB

**Step 1: Check how schedule is registered**

Read `api/routes/console.php` or `api/app/Console/Kernel.php` to find where to add the schedule entries. Add:

```php
$schedule->command('chain:sync-transactions')->hourly();
$schedule->command('chain:mark-unmatched')->daily();
```

**Step 2: Seed new permissions**

Run: `cd /Users/apple/projects/morgan/ustd/api && php artisan db:seed --class=PermissionSeeder`

Or manually insert via tinker:
```php
DB::table('permissions')->insertOrIgnore([
    ['id' => 36, 'role' => 1, 'group_name' => '鏈上交易管理', 'name' => '查看鏈上交易', 'created_at' => now(), 'updated_at' => now()],
    ['id' => 37, 'role' => 1, 'group_name' => '鏈上交易管理', 'name' => '管理鏈上交易', 'created_at' => now(), 'updated_at' => now()],
]);
```

**Step 3: Commit any schedule changes**

```bash
git add -A
git commit -m "feat: register chain tx schedule and seed permissions"
```

---

### Task 14: Verification

**Step 1: Run migration**

```bash
cd /Users/apple/projects/morgan/ustd/api && php artisan migrate
```

**Step 2: Verify artisan commands are registered**

```bash
php artisan list chain
```

Expected: Shows `chain:sync-transactions`, `chain:backfill-history`, `chain:mark-unmatched`.

**Step 3: TypeScript check**

```bash
cd /Users/apple/projects/morgan/ustd && pnpm -r typecheck
```

**Step 4: Test backfill on a single account (if available)**

```bash
cd /Users/apple/projects/morgan/ustd/api && php artisan chain:backfill-history --days=7 --account_id=1
```

**Step 5: Start admin dev server and verify page**

```bash
cd /Users/apple/projects/morgan/ustd/apps/admin && pnpm run local
```

Navigate to `/chain-transactions` and verify the page renders.

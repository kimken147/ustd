<?php

namespace Tests\Unit\Utils;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHistory;
use App\Utils\WalletHistoryRecorder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WalletHistoryRecorderTest extends TestCase
{
    use DatabaseTransactions;

    private WalletHistoryRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = new WalletHistoryRecorder();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createUser(array $overrides = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name' => 'test_user',
            'username' => 'test_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => User::ROLE_MERCHANT,
            'secret_key' => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return User::find($id);
    }

    private function createWallet(int $userId): Wallet
    {
        $id = DB::table('wallets')->insertGetId([
            'user_id' => $userId,
            'balance' => '1000.00',
            'frozen_balance' => '0.00',
            'profit' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Wallet::find($id);
    }

    // ---------------------------------------------------------------
    // recordSystem()
    // ---------------------------------------------------------------

    public function test_recordSystem_creates_wallet_history_with_zero_operator(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user->id);

        $delta = ['balance' => '100.00', 'profit' => '0.00', 'frozen_balance' => '0.00'];
        $result = ['balance' => '1100.00', 'profit' => '0.00', 'frozen_balance' => '0.00'];

        $history = $this->recorder->recordSystem(
            $wallet,
            WalletHistory::TYPE_DEPOSIT,
            $delta,
            $result,
            'system deposit'
        );

        $this->assertInstanceOf(WalletHistory::class, $history);
        $this->assertSame($user->id, $history->user_id);
        $this->assertSame(0, $history->operator_id);
        $this->assertSame(WalletHistory::TYPE_DEPOSIT, $history->type);
        $this->assertSame('system deposit', $history->note);
        $this->assertEquals($delta, $history->delta);
        $this->assertEquals($result, $history->result);
    }

    // ---------------------------------------------------------------
    // recordWithOperator()
    // ---------------------------------------------------------------

    public function test_recordWithOperator_creates_wallet_history_with_operator_id(): void
    {
        $user = $this->createUser();
        $operator = $this->createUser(['username' => 'operator_' . uniqid()]);
        $wallet = $this->createWallet($user->id);

        $delta = ['balance' => '200.00', 'profit' => '0.00', 'frozen_balance' => '0.00'];
        $result = ['balance' => '1200.00', 'profit' => '0.00', 'frozen_balance' => '0.00'];

        $history = $this->recorder->recordWithOperator(
            $wallet,
            $operator->id,
            WalletHistory::TYPE_SYSTEM_ADJUSTING,
            $delta,
            $result,
            'manual adjustment'
        );

        $this->assertInstanceOf(WalletHistory::class, $history);
        $this->assertSame($user->id, $history->user_id);
        $this->assertSame($operator->id, $history->operator_id);
        $this->assertSame(WalletHistory::TYPE_SYSTEM_ADJUSTING, $history->type);
        $this->assertSame('manual adjustment', $history->note);
    }

    // ---------------------------------------------------------------
    // recordSystem() — delta and result accuracy
    // ---------------------------------------------------------------

    public function test_recordSystem_stores_delta_and_result_correctly(): void
    {
        $user = $this->createUser();
        $wallet = $this->createWallet($user->id);

        $delta = ['balance' => '-50.00', 'profit' => '10.00', 'frozen_balance' => '5.00'];
        $result = ['balance' => '950.00', 'profit' => '10.00', 'frozen_balance' => '5.00'];

        $history = $this->recorder->recordSystem(
            $wallet,
            WalletHistory::TYPE_WITHHOLD,
            $delta,
            $result,
            'withhold note'
        );

        // Reload from DB to verify JSON serialization roundtrip
        $loaded = WalletHistory::find($history->id);
        $this->assertEquals($delta, $loaded->delta);
        $this->assertEquals($result, $loaded->result);
        $this->assertSame(WalletHistory::TYPE_WITHHOLD, $loaded->type);
    }
}

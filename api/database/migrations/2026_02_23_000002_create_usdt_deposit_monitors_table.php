<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usdt_deposit_monitors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->unique();
            $table->unsignedBigInteger('user_channel_account_id');
            $table->string('address', 100)->index();
            $table->string('chain_network', 10);
            $table->decimal('expected_amount', 20, 6);
            $table->decimal('received_amount', 20, 6)->default(0);
            $table->string('tx_hash', 100)->nullable();
            $table->tinyInteger('status')->default(0)->comment('0=pending 1=matched 2=confirmed 3=expired');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->foreign('user_channel_account_id')->references('id')->on('user_channel_accounts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usdt_deposit_monitors');
    }
};

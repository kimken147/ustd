<?php

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

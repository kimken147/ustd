<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fund_transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->string('batch_id', 36)->index()->comment('同一批次的識別碼');
            $table->unsignedBigInteger('source_account_id')->comment('來源帳號');
            $table->unsignedBigInteger('target_account_id')->comment('目標帳號');
            $table->string('source_address')->comment('來源地址');
            $table->string('target_address')->comment('目標地址');
            $table->decimal('amount', 20, 6)->comment('轉帳金額');
            $table->string('chain_network', 20)->default('trc20')->comment('鏈網路');
            $table->string('tx_hash')->nullable()->comment('鏈上交易 hash');
            $table->enum('status', ['pending', 'processing', 'success', 'failed'])->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人');
            $table->timestamps();

            $table->foreign('source_account_id')->references('id')->on('user_channel_accounts')->restrictOnDelete();
            $table->foreign('target_account_id')->references('id')->on('user_channel_accounts')->restrictOnDelete();
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fund_transfer_logs');
    }
};

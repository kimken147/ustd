<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransactionTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('from_id')->nullable();
            $table->unsignedBigInteger('to_id')->nullable();

            $table->unsignedTinyInteger('type');
            $table->unsignedTinyInteger('status');
            $table->unsignedTinyInteger('notify_status');

            $table->json('from_channel_account');
            $table->json('to_channel_account');

            $table->unsignedDecimal('amount', 11, 2);
            $table->unsignedDecimal('floating_amount', 11, 2);
            $table->unsignedDecimal('actual_amount', 11, 2)->default(0);

            $table->char('channel_code', 20)->nullable();
            $table->string('order_number', 50)->nullable();
            $table->string('note', 50)->nullable();

            $table->string('notify_url')->nullable();

            $table->timestamp('notified_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_fees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('transaction_id');

            $table->unsignedDecimal('profit', 11, 2)->default(0);
            $table->unsignedDecimal('actual_profit', 11, 2)->default(0);
            $table->unsignedDecimal('fee', 8, 2)->default(0);
            $table->unsignedDecimal('actual_fee', 8, 2)->default(0);

            $table->unique(['user_id', 'transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('transaction_fees');
    }
}

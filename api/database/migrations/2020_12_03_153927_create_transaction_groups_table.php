<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionGroupsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transaction_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('transaction_type');
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('worker_id');
            $table->timestamps();

            $table->unique(['transaction_type', 'owner_id', 'worker_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transaction_groups');
    }
}

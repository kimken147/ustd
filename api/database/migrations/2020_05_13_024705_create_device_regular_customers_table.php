<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDeviceRegularCustomersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('device_regular_customers', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id');
            $table->unsignedInteger('client_ipv4');
            $table->timestamps();

            $table->unique(['device_id', 'client_ipv4']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('device_regular_customers');
    }
}

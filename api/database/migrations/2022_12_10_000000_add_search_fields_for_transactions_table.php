<?php

use App\Models\UserChannelAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSearchFieldsForTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('_search1', 50)->after('deleted_at')->nullable();

            $table->index('_search1');
        });

        Schema::table('user_channel_account_audits', function (Blueprint $table) {
            $table->dropColumn('updated_by');
            $table->bigInteger('updated_by_user_id')->nullable();
            $table->bigInteger('updated_by_transaction_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('_search1');
        });

        Schema::table('user_channel_account_audits', function (Blueprint $table) {
            $table->text('updated_by')->nullabl();
            $table->dropColumn('updated_by_user_id');
            $table->dropColumn('updated_by_transaction_id');
        });
    }
}

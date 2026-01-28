<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransactionableColumnsToTransactions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add polymorphic columns
            $table->unsignedBigInteger('transactionable_id')->nullable()->after('total_amount');
            $table->string('transactionable_type')->nullable()->after('transactionable_id');

            // Add index for better performance
            $table->index(['transactionable_id', 'transactionable_type']);
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
            $table->dropIndex(['transactionable_id', 'transactionable_type']);
            $table->dropColumn(['transactionable_id', 'transactionable_type']);
        });
    }
}

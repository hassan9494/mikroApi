<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateAccountStatementDetailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_statement_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_statement_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('transaction_number')->nullable();          // e.g., order number
            $table->text('description')->nullable();                   // details text
            $table->enum('transaction_type', ['invoice', 'general_entry'])->default('invoice');
            $table->decimal('debtor', 10, 3)->default(0);              // amount owed
            $table->decimal('creditor', 10, 3)->default(0);             // amount paid
            $table->decimal('balance', 10, 3)->default(0);              // running balance after this row
            $table->timestamp('transaction_date')->nullable();          // date of transaction
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_statement_details');
    }
}

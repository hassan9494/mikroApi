<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBulkOrderCompletionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bulk_order_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('payment_method_id')->constrained()->onDelete('cascade');
            $table->decimal('total_calculated_amount', 10, 3)->default(0);
            $table->decimal('total_entered_amount', 10, 3)->default(0);
            $table->decimal('total_calculated_shipping', 10, 3)->default(0);
            $table->decimal('total_entered_shipping', 10, 3)->default(0);
            $table->decimal('total_calculated_commission', 10, 3)->default(0);
            $table->decimal('total_entered_commission', 10, 3)->default(0);
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('bulk_order_completions');
    }
}

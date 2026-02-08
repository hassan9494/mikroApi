<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBulkOrderCompletionOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bulk_order_completion_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bulk_order_completion_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->decimal('calculated_amount', 10, 3)->default(0);
            $table->decimal('entered_amount', 10, 3)->default(0);
            $table->decimal('calculated_shipping', 10, 3)->default(0);
            $table->decimal('entered_shipping', 10, 3)->default(0);
            $table->decimal('calculated_commission', 10, 3)->default(0);
            $table->decimal('entered_commission', 10, 3)->default(0);
            $table->timestamps();

//            $table->unique(['bulk_order_completion_id', 'order_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bulk_order_completion_orders');
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderHistoriesTable extends Migration
{
    public function up()
    {
        Schema::create('order_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action'); // created, updated, status_changed, printed, etc.
            $table->string('field')->nullable(); // Which field changed
            $table->text('old_value')->nullable(); // Old value
            $table->text('new_value')->nullable(); // New value
            $table->text('details')->nullable(); // JSON or text description of changes
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_histories');
    }
}

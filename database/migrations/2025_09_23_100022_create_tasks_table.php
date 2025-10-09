<?php
// database/migrations/2025_09_23_100022_create_tasks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTasksTable extends Migration
{
    public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['todo', 'inProgress', 'completed'])->default('todo');
            $table->integer('position')->default(0);
            $table->timestamp('due_date')->nullable();
            $table->integer('priority')->default(0);
            $table->json('labels')->nullable();
            $table->json('assignees')->nullable(); // Array of user IDs
            $table->json('comments')->nullable(); // Array of comments with user_id, content, timestamp
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'position']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tasks');
    }
}

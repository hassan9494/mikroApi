<?php
// database/migrations/2025_10_05_fix_task_status_enum.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixTaskStatusEnum extends Migration
{
    public function up()
    {
        // Change status column from ENUM to string to allow custom board names
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('status', 50)->default('todo')->change();
        });
    }

    public function down()
    {
        // Convert back to ENUM - be careful as this might lose data
        Schema::table('tasks', function (Blueprint $table) {
            // First update any custom statuses to 'todo'
            DB::table('tasks')->whereNotIn('status', ['todo', 'inProgress', 'completed'])->update(['status' => 'todo']);

            $table->enum('status', ['todo', 'inProgress', 'completed'])->default('todo')->change();
        });
    }
}

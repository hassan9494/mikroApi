<?php
// database/migrations/2025_10_05_fix_existing_tasks_board_ids.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixExistingTasksBoardIds extends Migration
{
    public function up()
    {
        // Get the default boards
        $todoBoard = DB::table('boards')->where('name', 'todo')->first();
        $inProgressBoard = DB::table('boards')->where('name', 'inProgress')->first();
        $completedBoard = DB::table('boards')->where('name', 'completed')->first();

        // Update tasks based on their status to set the correct board_id
        if ($todoBoard) {
            DB::table('tasks')
                ->where('status', 'todo')
                ->whereNull('board_id')
                ->update(['board_id' => $todoBoard->id]);
        }

        if ($inProgressBoard) {
            DB::table('tasks')
                ->where('status', 'inProgress')
                ->whereNull('board_id')
                ->update(['board_id' => $inProgressBoard->id]);
        }

        if ($completedBoard) {
            DB::table('tasks')
                ->where('status', 'completed')
                ->whereNull('board_id')
                ->update(['board_id' => $completedBoard->id]);
        }

        // For any tasks that still have null board_id but have a status that matches a board name
        $boards = DB::table('boards')->get();

        foreach ($boards as $board) {
            DB::table('tasks')
                ->where('status', $board->name)
                ->whereNull('board_id')
                ->update(['board_id' => $board->id]);
        }
    }

    public function down()
    {
        // This migration cannot be easily reversed
        // We'll set board_id to null but keep the status
        DB::table('tasks')->update(['board_id' => null]);
    }
}

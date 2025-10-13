<?php
// database/migrations/2025_09_24_add_assignees_and_comments_to_tasks_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAssigneesAndCommentsToTasksTable extends Migration
{
    public function up()
    {
        Schema::table('tasks', function (Blueprint $table) {
//            $table->json('assignees')->nullable()->after('labels');
//            $table->json('comments')->nullable()->after('assignees');
        });
    }

    public function down()
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['assignees', 'comments']);
        });
    }
}

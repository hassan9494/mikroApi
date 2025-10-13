<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Board;
use App\Models\User;

class BoardSeeder extends Seeder
{
    public function run()
    {
        $superAdmin = User::role('super')->first();

        if (!$superAdmin) {
            $superAdmin = User::first();
            if (!$superAdmin) {
                // Create a default super admin if none exists
                $superAdmin = User::create([
                    'name' => 'Super Admin',
                    'email' => 'super@admin.com',
                    'password' => bcrypt('password'),
                    'status' => 1
                ]);
                $superAdmin->assignRole('super');
            }
        }

        $defaultBoards = [
            [
                'name' => 'todo',
                'color' => '#6c757d',
                'order' => 1,
                'is_default' => true,
                'is_active' => true
            ],
            [
                'name' => 'inProgress',
                'color' => '#ffc107',
                'order' => 2,
                'is_default' => true,
                'is_active' => true
            ],
            [
                'name' => 'completed',
                'color' => '#28a745',
                'order' => 3,
                'is_default' => true,
                'is_active' => true
            ]
        ];

        foreach ($defaultBoards as $boardData) {
            Board::firstOrCreate(
                ['name' => $boardData['name']],
                [
                    'color' => $boardData['color'],
                    'order' => $boardData['order'],
                    'is_default' => $boardData['is_default'],
                    'is_active' => $boardData['is_active'],
                    'created_by' => $superAdmin->id
                ]
            );
        }
    }
}

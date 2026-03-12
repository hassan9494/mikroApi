<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AssignUserRole extends Command
{
    protected $signature = 'user:assign-role {--dry-run : Show what will be changed without saving}';

    protected $description = 'Assign "user" role to all users that have no role';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('DRY RUN - No changes will be saved');
        }

        $usersWithoutRole = User::doesntHave('roles')->get();

        $this->info("Found {$usersWithoutRole->count()} users without any role.");

        $updated = 0;

        foreach ($usersWithoutRole as $user) {
            $this->line("User #{$user->id} - {$user->name} ({$user->email})");

            if (!$isDryRun) {
                $user->assignRole('user');
            }

            $updated++;
        }

        $this->newLine();

        if ($isDryRun) {
            $this->warn("{$updated} users would be assigned the 'user' role. Run without --dry-run to apply.");
        } else {
            $this->info("{$updated} users assigned the 'user' role successfully!");
        }

        return 0;
    }
}

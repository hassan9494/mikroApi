<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanExports extends Command
{
    protected $signature = 'clean:exports';
    protected $description = 'Clean up old export files';

    public function handle()
    {
        $directories = Storage::directories('exports');
        $now = now();

        foreach ($directories as $dir) {
            if ($now->diffInHours(Storage::lastModified($dir)) > 24) {
                Storage::deleteDirectory($dir);
            }
        }

        $this->info('Cleaned ' . count($directories) . ' export directories');
        return 0;
    }
}

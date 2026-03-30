<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        if ((getenv('APP_ENV') ?: $_ENV['APP_ENV'] ?? null) === 'testing') {
            $databasePath = __DIR__.'/../database/database_testing.sqlite';

            if (! file_exists($databasePath)) {
                touch($databasePath);
            }
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}

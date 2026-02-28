<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->ensureEnvironmentFileExists();
    }

    /**
     * Create .env from .env.example if .env does not exist.
     * This allows `php artisan key:generate` to run successfully.
     */
    private function ensureEnvironmentFileExists(): void
    {
        $envPath = $this->app->environmentFilePath();
        $examplePath = $envPath . '.example';

        if (! file_exists($envPath) && file_exists($examplePath)) {
            copy($examplePath, $envPath);
        }
    }
}

<?php

namespace Divblox\Providers;

use Illuminate\Support\ServiceProvider;
use Divblox\Console\DataDesignImporter;

class DivbloxServiceProvider extends ServiceProvider
{
    public function boot() {
        $this->publishes([
            __DIR__.'/../Config/divblox.php' => config_path('divblox.php'),
        ]);
    }
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->commands(DataDesignImporter::class);
    }
}
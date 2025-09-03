<?php

namespace Divblox\Providers;

use Illuminate\Support\ServiceProvider;
use Divblox\Console\DataModelImporter;

class DivbloxServiceProvider extends ServiceProvider
{
    public function boot() {
        
    }
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->commands(DataModelImporter::class);
    }
}
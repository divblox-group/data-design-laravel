<?php

namespace Ivanbekker\DataDesignLaravel\Providers;

use Illuminate\Support\ServiceProvider;
use Ivanbekker\DataDesignLaravel\Console\DataModelImporter;

class DivbloxServiceProvider extends ServiceProvider
{
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
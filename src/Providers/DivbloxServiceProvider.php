<?php

namespace Divblox\Providers;

use Illuminate\Support\ServiceProvider;
use DivbloxDataModelImporter;

class DivbloxServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->commands(DivbloxDataModelImporter::class);
    }
}
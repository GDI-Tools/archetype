<?php

namespace Archetype\Vendor\Illuminate\Pipeline;

use Archetype\Vendor\Illuminate\Contracts\Pipeline\Hub as PipelineHubContract;
use Archetype\Vendor\Illuminate\Contracts\Support\DeferrableProvider;
use Archetype\Vendor\Illuminate\Support\ServiceProvider;
class PipelineServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(PipelineHubContract::class, Hub::class);
        $this->app->bind('pipeline', fn($app) => new Pipeline($app));
    }
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [PipelineHubContract::class, 'pipeline'];
    }
}

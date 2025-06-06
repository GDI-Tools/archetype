<?php

namespace Archetype\Vendor\Illuminate\Events;

use Archetype\Vendor\Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Archetype\Vendor\Illuminate\Support\ServiceProvider;
class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('events', function ($app) {
            return (new Dispatcher($app))->setQueueResolver(function () use ($app) {
                return $app->make(QueueFactoryContract::class);
            })->setTransactionManagerResolver(function () use ($app) {
                return $app->bound('db.transactions') ? $app->make('db.transactions') : null;
            });
        });
    }
}

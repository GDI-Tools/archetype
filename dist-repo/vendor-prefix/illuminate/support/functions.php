<?php

namespace Archetype\Vendor\Illuminate\Support;

use Archetype\Vendor\Illuminate\Support\Defer\DeferredCallback;
use Archetype\Vendor\Illuminate\Support\Defer\DeferredCallbackCollection;
use Archetype\Vendor\Symfony\Component\Process\PhpExecutableFinder;
if (!function_exists('Archetype\Vendor\Illuminate\Support\defer')) {
    /**
     * Defer execution of the given callback.
     *
     * @param  callable|null  $callback
     * @param  string|null  $name
     * @param  bool  $always
     * @return \Illuminate\Support\Defer\DeferredCallback
     */
    function defer(?callable $callback = null, ?string $name = null, bool $always = \false)
    {
        if ($callback === null) {
            return app(DeferredCallbackCollection::class);
        }
        return tap(new DeferredCallback($callback, $name, $always), fn($deferred) => app(DeferredCallbackCollection::class)[] = $deferred);
    }
}
if (!function_exists('Archetype\Vendor\Illuminate\Support\php_binary')) {
    /**
     * Determine the PHP Binary.
     *
     * @return string
     */
    function php_binary()
    {
        return (new PhpExecutableFinder())->find(\false) ?: 'php';
    }
}
if (!function_exists('Archetype\Vendor\Illuminate\Support\artisan_binary')) {
    /**
     * Determine the proper Artisan executable.
     *
     * @return string
     */
    function artisan_binary()
    {
        return defined('Archetype\Vendor\ARTISAN_BINARY') ? ARTISAN_BINARY : 'artisan';
    }
}

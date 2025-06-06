<?php

namespace Archetype\Vendor\Illuminate\Support;

class DefaultProviders
{
    /**
     * The current providers.
     *
     * @var array
     */
    protected $providers;
    /**
     * Create a new default provider collection.
     */
    public function __construct(?array $providers = null)
    {
        $this->providers = $providers ?: [\Archetype\Vendor\Illuminate\Auth\AuthServiceProvider::class, \Archetype\Vendor\Illuminate\Broadcasting\BroadcastServiceProvider::class, \Archetype\Vendor\Illuminate\Bus\BusServiceProvider::class, \Archetype\Vendor\Illuminate\Cache\CacheServiceProvider::class, \Archetype\Vendor\Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class, \Archetype\Vendor\Illuminate\Concurrency\ConcurrencyServiceProvider::class, \Archetype\Vendor\Illuminate\Cookie\CookieServiceProvider::class, \Archetype\Vendor\Illuminate\Database\DatabaseServiceProvider::class, \Archetype\Vendor\Illuminate\Encryption\EncryptionServiceProvider::class, \Archetype\Vendor\Illuminate\Filesystem\FilesystemServiceProvider::class, \Archetype\Vendor\Illuminate\Foundation\Providers\FoundationServiceProvider::class, \Archetype\Vendor\Illuminate\Hashing\HashServiceProvider::class, \Archetype\Vendor\Illuminate\Mail\MailServiceProvider::class, \Archetype\Vendor\Illuminate\Notifications\NotificationServiceProvider::class, \Archetype\Vendor\Illuminate\Pagination\PaginationServiceProvider::class, \Archetype\Vendor\Illuminate\Auth\Passwords\PasswordResetServiceProvider::class, \Archetype\Vendor\Illuminate\Pipeline\PipelineServiceProvider::class, \Archetype\Vendor\Illuminate\Queue\QueueServiceProvider::class, \Archetype\Vendor\Illuminate\Redis\RedisServiceProvider::class, \Archetype\Vendor\Illuminate\Session\SessionServiceProvider::class, \Archetype\Vendor\Illuminate\Translation\TranslationServiceProvider::class, \Archetype\Vendor\Illuminate\Validation\ValidationServiceProvider::class, \Archetype\Vendor\Illuminate\View\ViewServiceProvider::class];
    }
    /**
     * Merge the given providers into the provider collection.
     *
     * @param  array  $providers
     * @return static
     */
    public function merge(array $providers)
    {
        $this->providers = array_merge($this->providers, $providers);
        return new static($this->providers);
    }
    /**
     * Replace the given providers with other providers.
     *
     * @param  array  $replacements
     * @return static
     */
    public function replace(array $replacements)
    {
        $current = new Collection($this->providers);
        foreach ($replacements as $from => $to) {
            $key = $current->search($from);
            $current = is_int($key) ? $current->replace([$key => $to]) : $current;
        }
        return new static($current->values()->toArray());
    }
    /**
     * Disable the given providers.
     *
     * @param  array  $providers
     * @return static
     */
    public function except(array $providers)
    {
        return new static((new Collection($this->providers))->reject(fn($p) => in_array($p, $providers))->values()->toArray());
    }
    /**
     * Convert the provider collection to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->providers;
    }
}

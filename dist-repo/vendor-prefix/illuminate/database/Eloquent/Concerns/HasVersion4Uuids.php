<?php

namespace Archetype\Vendor\Illuminate\Database\Eloquent\Concerns;

use Archetype\Vendor\Illuminate\Support\Str;
trait HasVersion4Uuids
{
    use HasUuids;
    /**
     * Generate a new UUID (version 4) for the model.
     *
     * @return string
     */
    public function newUniqueId()
    {
        return (string) Str::orderedUuid();
    }
}

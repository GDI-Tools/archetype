<?php

namespace Archetype\Vendor\Illuminate\Bus\Events;

use Archetype\Vendor\Illuminate\Bus\Batch;
class BatchDispatched
{
    /**
     * Create a new event instance.
     *
     * @param  \Illuminate\Bus\Batch  $batch  The batch instance.
     */
    public function __construct(public Batch $batch)
    {
    }
}

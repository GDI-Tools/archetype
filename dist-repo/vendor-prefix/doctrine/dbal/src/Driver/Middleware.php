<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\DBAL\Driver;

use Archetype\Vendor\Doctrine\DBAL\Driver;
interface Middleware
{
    public function wrap(Driver $driver): Driver;
}

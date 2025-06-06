<?php

namespace Archetype\Vendor\Doctrine\DBAL\Tools\Console;

use Archetype\Vendor\Doctrine\DBAL\Connection;
interface ConnectionProvider
{
    public function getDefaultConnection(): Connection;
    /** @throws ConnectionNotFound in case a connection with the given name does not exist. */
    public function getConnection(string $name): Connection;
}

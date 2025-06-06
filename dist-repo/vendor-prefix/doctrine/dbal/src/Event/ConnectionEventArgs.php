<?php

namespace Archetype\Vendor\Doctrine\DBAL\Event;

use Archetype\Vendor\Doctrine\Common\EventArgs;
use Archetype\Vendor\Doctrine\DBAL\Connection;
/**
 * Event Arguments used when a Driver connection is established inside Doctrine\DBAL\Connection.
 *
 * @deprecated
 */
class ConnectionEventArgs extends EventArgs
{
    private Connection $connection;
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    /** @return Connection */
    public function getConnection()
    {
        return $this->connection;
    }
}

<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\DBAL\Event;

use Archetype\Vendor\Doctrine\Common\EventArgs;
use Archetype\Vendor\Doctrine\DBAL\Connection;
/** @deprecated */
abstract class TransactionEventArgs extends EventArgs
{
    private Connection $connection;
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}

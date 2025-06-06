<?php

namespace Archetype\Vendor\Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware;

use Archetype\Vendor\Doctrine\DBAL\Driver;
use Archetype\Vendor\Doctrine\DBAL\Driver\Connection;
use Archetype\Vendor\Doctrine\DBAL\Driver\Middleware;
use Archetype\Vendor\Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;
class EnableForeignKeys implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware
        {
            /**
             * {@inheritDoc}
             */
            public function connect(
                #[SensitiveParameter]
                array $params
            ): Connection
            {
                $connection = parent::connect($params);
                $connection->exec('PRAGMA foreign_keys=ON');
                return $connection;
            }
        };
    }
}

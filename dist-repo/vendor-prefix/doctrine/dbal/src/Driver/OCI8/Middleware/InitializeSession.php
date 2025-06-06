<?php

namespace Archetype\Vendor\Doctrine\DBAL\Driver\OCI8\Middleware;

use Archetype\Vendor\Doctrine\DBAL\Driver;
use Archetype\Vendor\Doctrine\DBAL\Driver\Connection;
use Archetype\Vendor\Doctrine\DBAL\Driver\Middleware;
use Archetype\Vendor\Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use SensitiveParameter;
class InitializeSession implements Middleware
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
                $connection->exec('ALTER SESSION SET' . " NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'" . " NLS_TIME_FORMAT = 'HH24:MI:SS'" . " NLS_TIMESTAMP_FORMAT = 'YYYY-MM-DD HH24:MI:SS'" . " NLS_TIMESTAMP_TZ_FORMAT = 'YYYY-MM-DD HH24:MI:SS TZH:TZM'" . " NLS_NUMERIC_CHARACTERS = '.,'");
                return $connection;
            }
        };
    }
}

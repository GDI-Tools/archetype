<?php

namespace Archetype\Vendor\Doctrine\DBAL\Event\Listeners;

use Archetype\Vendor\Doctrine\Common\EventSubscriber;
use Archetype\Vendor\Doctrine\DBAL\Event\ConnectionEventArgs;
use Archetype\Vendor\Doctrine\DBAL\Events;
use Archetype\Vendor\Doctrine\DBAL\Exception;
/** @deprecated Use {@see \Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys} instead. */
class SQLiteSessionInit implements EventSubscriber
{
    /**
     * @return void
     *
     * @throws Exception
     */
    public function postConnect(ConnectionEventArgs $args)
    {
        $args->getConnection()->executeStatement('PRAGMA foreign_keys=ON');
    }
    /**
     * {@inheritDoc}
     */
    public function getSubscribedEvents()
    {
        return [Events::postConnect];
    }
}

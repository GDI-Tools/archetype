<?php

namespace Archetype\Vendor\Doctrine\DBAL\Event\Listeners;

use Archetype\Vendor\Doctrine\Common\EventSubscriber;
use Archetype\Vendor\Doctrine\DBAL\Event\ConnectionEventArgs;
use Archetype\Vendor\Doctrine\DBAL\Events;
use Archetype\Vendor\Doctrine\DBAL\Exception;
/**
 * Session init listener for executing a single SQL statement right after a connection is opened.
 *
 * @deprecated Implement a middleware instead.
 */
class SQLSessionInit implements EventSubscriber
{
    /** @var string */
    protected $sql;
    /** @param string $sql */
    public function __construct($sql)
    {
        $this->sql = $sql;
    }
    /**
     * @return void
     *
     * @throws Exception
     */
    public function postConnect(ConnectionEventArgs $args)
    {
        $args->getConnection()->executeStatement($this->sql);
    }
    /**
     * {@inheritDoc}
     */
    public function getSubscribedEvents()
    {
        return [Events::postConnect];
    }
}

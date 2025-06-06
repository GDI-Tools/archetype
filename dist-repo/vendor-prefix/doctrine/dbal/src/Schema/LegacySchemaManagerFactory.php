<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\DBAL\Schema;

use Archetype\Vendor\Doctrine\DBAL\Connection;
/** @internal Will be removed in 4.0. */
final class LegacySchemaManagerFactory implements SchemaManagerFactory
{
    public function createSchemaManager(Connection $connection): AbstractSchemaManager
    {
        return $connection->getDriver()->getSchemaManager($connection, $connection->getDatabasePlatform());
    }
}

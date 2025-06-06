<?php

namespace Archetype\Vendor\Doctrine\DBAL\Driver;

use Archetype\Vendor\Doctrine\DBAL\Connection;
use Archetype\Vendor\Doctrine\DBAL\Driver;
use Archetype\Vendor\Doctrine\DBAL\Driver\API\ExceptionConverter;
use Archetype\Vendor\Doctrine\DBAL\Driver\API\SQLite;
use Archetype\Vendor\Doctrine\DBAL\Platforms\AbstractPlatform;
use Archetype\Vendor\Doctrine\DBAL\Platforms\SqlitePlatform;
use Archetype\Vendor\Doctrine\DBAL\Schema\SqliteSchemaManager;
use Archetype\Vendor\Doctrine\Deprecations\Deprecation;
use function assert;
/**
 * Abstract base implementation of the {@see Doctrine\DBAL\Driver} interface for SQLite based drivers.
 */
abstract class AbstractSQLiteDriver implements Driver
{
    /**
     * {@inheritDoc}
     */
    public function getDatabasePlatform()
    {
        return new SqlitePlatform();
    }
    /**
     * {@inheritDoc}
     *
     * @deprecated Use {@link SqlitePlatform::createSchemaManager()} instead.
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform)
    {
        Deprecation::triggerIfCalledFromOutside('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/5458', 'AbstractSQLiteDriver::getSchemaManager() is deprecated.' . ' Use SqlitePlatform::createSchemaManager() instead.');
        assert($platform instanceof SqlitePlatform);
        return new SqliteSchemaManager($conn, $platform);
    }
    public function getExceptionConverter(): ExceptionConverter
    {
        return new SQLite\ExceptionConverter();
    }
}

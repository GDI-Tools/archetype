<?php

namespace Archetype\Vendor\Doctrine\DBAL\Driver;

use Archetype\Vendor\Doctrine\DBAL\Connection;
use Archetype\Vendor\Doctrine\DBAL\Driver;
use Archetype\Vendor\Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Archetype\Vendor\Doctrine\DBAL\Driver\API\SQLSrv\ExceptionConverter;
use Archetype\Vendor\Doctrine\DBAL\Platforms\AbstractPlatform;
use Archetype\Vendor\Doctrine\DBAL\Platforms\SQLServer2012Platform;
use Archetype\Vendor\Doctrine\DBAL\Platforms\SQLServerPlatform;
use Archetype\Vendor\Doctrine\DBAL\Schema\SQLServerSchemaManager;
use Archetype\Vendor\Doctrine\Deprecations\Deprecation;
use function assert;
/**
 * Abstract base implementation of the {@see Driver} interface for Microsoft SQL Server based drivers.
 */
abstract class AbstractSQLServerDriver implements Driver
{
    /**
     * {@inheritDoc}
     */
    public function getDatabasePlatform()
    {
        return new SQLServer2012Platform();
    }
    /**
     * {@inheritDoc}
     *
     * @deprecated Use {@link SQLServerPlatform::createSchemaManager()} instead.
     */
    public function getSchemaManager(Connection $conn, AbstractPlatform $platform)
    {
        Deprecation::triggerIfCalledFromOutside('doctrine/dbal', 'https://github.com/doctrine/dbal/pull/5458', 'AbstractSQLServerDriver::getSchemaManager() is deprecated.' . ' Use SQLServerPlatform::createSchemaManager() instead.');
        assert($platform instanceof SQLServerPlatform);
        return new SQLServerSchemaManager($conn, $platform);
    }
    public function getExceptionConverter(): ExceptionConverterInterface
    {
        return new ExceptionConverter();
    }
}

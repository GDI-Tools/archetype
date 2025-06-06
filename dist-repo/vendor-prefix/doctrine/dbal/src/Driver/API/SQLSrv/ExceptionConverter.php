<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\DBAL\Driver\API\SQLSrv;

use Archetype\Vendor\Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Archetype\Vendor\Doctrine\DBAL\Driver\Exception;
use Archetype\Vendor\Doctrine\DBAL\Exception\ConnectionException;
use Archetype\Vendor\Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Archetype\Vendor\Doctrine\DBAL\Exception\DriverException;
use Archetype\Vendor\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Archetype\Vendor\Doctrine\DBAL\Exception\InvalidFieldNameException;
use Archetype\Vendor\Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Archetype\Vendor\Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Archetype\Vendor\Doctrine\DBAL\Exception\SyntaxErrorException;
use Archetype\Vendor\Doctrine\DBAL\Exception\TableExistsException;
use Archetype\Vendor\Doctrine\DBAL\Exception\TableNotFoundException;
use Archetype\Vendor\Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Archetype\Vendor\Doctrine\DBAL\Query;
/**
 * @internal
 *
 * @link https://docs.microsoft.com/en-us/sql/relational-databases/errors-events/database-engine-events-and-errors
 */
final class ExceptionConverter implements ExceptionConverterInterface
{
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        switch ($exception->getCode()) {
            case 102:
                return new SyntaxErrorException($exception, $query);
            case 207:
                return new InvalidFieldNameException($exception, $query);
            case 208:
                return new TableNotFoundException($exception, $query);
            case 209:
                return new NonUniqueFieldNameException($exception, $query);
            case 515:
                return new NotNullConstraintViolationException($exception, $query);
            case 547:
            case 4712:
                return new ForeignKeyConstraintViolationException($exception, $query);
            case 2601:
            case 2627:
                return new UniqueConstraintViolationException($exception, $query);
            case 2714:
                return new TableExistsException($exception, $query);
            case 3701:
            case 15151:
                return new DatabaseObjectNotFoundException($exception, $query);
            case 11001:
            case 18456:
                return new ConnectionException($exception, $query);
        }
        return new DriverException($exception, $query);
    }
}

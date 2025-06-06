<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\DBAL\Driver\API\SQLite;

use Archetype\Vendor\Doctrine\DBAL\Driver\API\ExceptionConverter as ExceptionConverterInterface;
use Archetype\Vendor\Doctrine\DBAL\Driver\Exception;
use Archetype\Vendor\Doctrine\DBAL\Exception\ConnectionException;
use Archetype\Vendor\Doctrine\DBAL\Exception\DriverException;
use Archetype\Vendor\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Archetype\Vendor\Doctrine\DBAL\Exception\InvalidFieldNameException;
use Archetype\Vendor\Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Archetype\Vendor\Doctrine\DBAL\Exception\NonUniqueFieldNameException;
use Archetype\Vendor\Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Archetype\Vendor\Doctrine\DBAL\Exception\ReadOnlyException;
use Archetype\Vendor\Doctrine\DBAL\Exception\SyntaxErrorException;
use Archetype\Vendor\Doctrine\DBAL\Exception\TableExistsException;
use Archetype\Vendor\Doctrine\DBAL\Exception\TableNotFoundException;
use Archetype\Vendor\Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Archetype\Vendor\Doctrine\DBAL\Query;
use function strpos;
/** @internal */
final class ExceptionConverter implements ExceptionConverterInterface
{
    /** @link http://www.sqlite.org/c3ref/c_abort.html */
    public function convert(Exception $exception, ?Query $query): DriverException
    {
        if (strpos($exception->getMessage(), 'database is locked') !== \false) {
            return new LockWaitTimeoutException($exception, $query);
        }
        if (strpos($exception->getMessage(), 'must be unique') !== \false || strpos($exception->getMessage(), 'is not unique') !== \false || strpos($exception->getMessage(), 'are not unique') !== \false || strpos($exception->getMessage(), 'UNIQUE constraint failed') !== \false) {
            return new UniqueConstraintViolationException($exception, $query);
        }
        if (strpos($exception->getMessage(), 'may not be NULL') !== \false || strpos($exception->getMessage(), 'NOT NULL constraint failed') !== \false) {
            return new NotNullConstraintViolationException($exception, $query);
        }
        if (strpos($exception->getMessage(), 'no such table:') !== \false) {
            return new TableNotFoundException($exception, $query);
        }
        if (strpos($exception->getMessage(), 'already exists') !== \false) {
            return new TableExistsException($exception, $query);
        }
        if (strpos($exception->getMessage(), 'has no column named') !== \false) {
            return new InvalidFieldNameException($exception, $query);
        }
        if (strpos($exception->getMessage(), 'ambiguous column name') !== \false) {
            return new NonUniqueFieldNameException($exception, $query);
        }
        if (strpos($exception->getMessage(), 'syntax error') !== \false) {
            return new SyntaxErrorException($exception, $query);
        }
        if (strpos($exception->getMessage(), 'attempt to write a readonly database') !== \false) {
            return new ReadOnlyException($exception, $query);
        }
        if (strpos($exception->getMessage(), 'unable to open database file') !== \false) {
            return new ConnectionException($exception, $query);
        }
        if (strpos($exception->getMessage(), 'FOREIGN KEY constraint failed') !== \false) {
            return new ForeignKeyConstraintViolationException($exception, $query);
        }
        return new DriverException($exception, $query);
    }
}

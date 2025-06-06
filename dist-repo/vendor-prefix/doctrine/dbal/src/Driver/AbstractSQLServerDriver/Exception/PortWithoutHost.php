<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\DBAL\Driver\AbstractSQLServerDriver\Exception;

use Archetype\Vendor\Doctrine\DBAL\Driver\AbstractException;
/** @internal */
final class PortWithoutHost extends AbstractException
{
    public static function new(): self
    {
        return new self('Connection port specified without the host');
    }
}

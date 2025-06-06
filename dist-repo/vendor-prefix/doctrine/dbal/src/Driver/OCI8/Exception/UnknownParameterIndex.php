<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\DBAL\Driver\OCI8\Exception;

use Archetype\Vendor\Doctrine\DBAL\Driver\AbstractException;
use function sprintf;
/** @internal */
final class UnknownParameterIndex extends AbstractException
{
    public static function new(int $index): self
    {
        return new self(sprintf('Could not find variable mapping with index %d, in the SQL statement', $index));
    }
}

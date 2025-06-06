<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\DBAL\Driver\Mysqli\Exception;

use Archetype\Vendor\Doctrine\DBAL\Driver\AbstractException;
use function sprintf;
/** @internal */
final class InvalidOption extends AbstractException
{
    /** @param mixed $value */
    public static function fromOption(int $option, $value): self
    {
        return new self(sprintf('Failed to set option %d with value "%s"', $option, $value));
    }
}

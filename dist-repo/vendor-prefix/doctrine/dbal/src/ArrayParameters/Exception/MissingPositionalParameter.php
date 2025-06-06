<?php

namespace Archetype\Vendor\Doctrine\DBAL\ArrayParameters\Exception;

use Archetype\Vendor\Doctrine\DBAL\ArrayParameters\Exception;
use LogicException;
use function sprintf;
/** @internal */
class MissingPositionalParameter extends LogicException implements Exception
{
    public static function new(int $index): self
    {
        return new self(sprintf('Positional parameter at index %d does not have a bound value.', $index));
    }
}

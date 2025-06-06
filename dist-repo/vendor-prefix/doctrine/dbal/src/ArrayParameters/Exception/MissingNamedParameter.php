<?php

namespace Archetype\Vendor\Doctrine\DBAL\ArrayParameters\Exception;

use Archetype\Vendor\Doctrine\DBAL\ArrayParameters\Exception;
use LogicException;
use function sprintf;
class MissingNamedParameter extends LogicException implements Exception
{
    public static function new(string $name): self
    {
        return new self(sprintf('Named parameter "%s" does not have a bound value.', $name));
    }
}

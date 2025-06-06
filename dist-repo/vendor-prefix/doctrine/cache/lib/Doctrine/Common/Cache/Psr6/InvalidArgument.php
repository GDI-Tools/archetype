<?php

namespace Archetype\Vendor\Doctrine\Common\Cache\Psr6;

use InvalidArgumentException;
use Archetype\Vendor\Psr\Cache\InvalidArgumentException as PsrInvalidArgumentException;
/**
 * @internal
 */
final class InvalidArgument extends InvalidArgumentException implements PsrInvalidArgumentException
{
}

<?php

/**
 * This file is part of the ramsey/uuid library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) Ben Ramsey <ben@benramsey.com>
 * @license http://opensource.org/licenses/MIT MIT
 */
declare (strict_types=1);
namespace Archetype\Vendor\Ramsey\Uuid\Converter\Number;

use Archetype\Vendor\Ramsey\Uuid\Converter\NumberConverterInterface;
use Archetype\Vendor\Ramsey\Uuid\Math\CalculatorInterface;
use Archetype\Vendor\Ramsey\Uuid\Type\Integer as IntegerObject;
/**
 * GenericNumberConverter uses the provided calculator to convert decimal numbers to and from hexadecimal values
 *
 * @immutable
 */
class GenericNumberConverter implements NumberConverterInterface
{
    public function __construct(private CalculatorInterface $calculator)
    {
    }
    public function fromHex(string $hex): string
    {
        return $this->calculator->fromBase($hex, 16)->toString();
    }
    public function toHex(string $number): string
    {
        /** @phpstan-ignore-next-line PHPStan complains that this is not a non-empty-string. */
        return $this->calculator->toBase(new IntegerObject($number), 16);
    }
}

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
namespace Archetype\Vendor\Ramsey\Uuid\Nonstandard;

use Archetype\Vendor\Ramsey\Uuid\Codec\CodecInterface;
use Archetype\Vendor\Ramsey\Uuid\Converter\NumberConverterInterface;
use Archetype\Vendor\Ramsey\Uuid\Converter\TimeConverterInterface;
use Archetype\Vendor\Ramsey\Uuid\Uuid as BaseUuid;
/**
 * Nonstandard\Uuid is a UUID that doesn't conform to RFC 9562 (formerly RFC 4122)
 *
 * @immutable
 */
final class Uuid extends BaseUuid
{
    public function __construct(Fields $fields, NumberConverterInterface $numberConverter, CodecInterface $codec, TimeConverterInterface $timeConverter)
    {
        parent::__construct($fields, $numberConverter, $codec, $timeConverter);
    }
}

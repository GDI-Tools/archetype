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
namespace Archetype\Vendor\Ramsey\Uuid\Generator;

use Archetype\Vendor\Ramsey\Uuid\Converter\TimeConverterInterface;
use Archetype\Vendor\Ramsey\Uuid\Provider\NodeProviderInterface;
use Archetype\Vendor\Ramsey\Uuid\Provider\TimeProviderInterface;
/**
 * TimeGeneratorFactory retrieves a default time generator, based on the environment
 */
class TimeGeneratorFactory
{
    public function __construct(private NodeProviderInterface $nodeProvider, private TimeConverterInterface $timeConverter, private TimeProviderInterface $timeProvider)
    {
    }
    /**
     * Returns a default time generator, based on the current environment
     */
    public function getGenerator(): TimeGeneratorInterface
    {
        return new DefaultTimeGenerator($this->nodeProvider, $this->timeConverter, $this->timeProvider);
    }
}

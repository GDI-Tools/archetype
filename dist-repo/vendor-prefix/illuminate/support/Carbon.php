<?php

namespace Archetype\Vendor\Illuminate\Support;

use Archetype\Vendor\Carbon\Carbon as BaseCarbon;
use Archetype\Vendor\Carbon\CarbonImmutable as BaseCarbonImmutable;
use Archetype\Vendor\Illuminate\Support\Traits\Conditionable;
use Archetype\Vendor\Illuminate\Support\Traits\Dumpable;
use Archetype\Vendor\Ramsey\Uuid\Uuid;
use Archetype\Vendor\Symfony\Component\Uid\Ulid;
class Carbon extends BaseCarbon
{
    use Conditionable, Dumpable;
    /**
     * {@inheritdoc}
     */
    public static function setTestNow(mixed $testNow = null): void
    {
        BaseCarbon::setTestNow($testNow);
        BaseCarbonImmutable::setTestNow($testNow);
    }
    /**
     * Create a Carbon instance from a given ordered UUID or ULID.
     */
    public static function createFromId(Uuid|Ulid|string $id): static
    {
        if (is_string($id)) {
            $id = Ulid::isValid($id) ? Ulid::fromString($id) : Uuid::fromString($id);
        }
        return static::createFromInterface($id->getDateTime());
    }
}

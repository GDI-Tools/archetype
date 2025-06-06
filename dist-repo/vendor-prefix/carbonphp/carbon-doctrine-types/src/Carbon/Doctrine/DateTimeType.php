<?php

namespace Archetype\Vendor\Carbon\Doctrine;

use Archetype\Vendor\Carbon\Carbon;
use Archetype\Vendor\Doctrine\DBAL\Types\VarDateTimeType;
class DateTimeType extends VarDateTimeType implements CarbonDoctrineType
{
    /** @use CarbonTypeConverter<Carbon> */
    use CarbonTypeConverter;
}

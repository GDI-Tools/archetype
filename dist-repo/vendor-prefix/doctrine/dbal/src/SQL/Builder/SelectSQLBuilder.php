<?php

namespace Archetype\Vendor\Doctrine\DBAL\SQL\Builder;

use Archetype\Vendor\Doctrine\DBAL\Exception;
use Archetype\Vendor\Doctrine\DBAL\Query\SelectQuery;
interface SelectSQLBuilder
{
    /** @throws Exception */
    public function buildSQL(SelectQuery $query): string;
}

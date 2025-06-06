<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;

use Archetype\Vendor\Doctrine\DBAL\Connection;
use Archetype\Vendor\Doctrine\DBAL\Exception;
use Archetype\Vendor\Doctrine\DBAL\Platforms\MySQL\CollationMetadataProvider;
/** @internal */
final class ConnectionCollationMetadataProvider implements CollationMetadataProvider
{
    /** @var Connection */
    private $connection;
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
    /** @throws Exception */
    public function getCollationCharset(string $collation): ?string
    {
        $charset = $this->connection->fetchOne(<<<'SQL'
SELECT CHARACTER_SET_NAME
FROM information_schema.COLLATIONS
WHERE COLLATION_NAME = ?;
SQL
, [$collation]);
        if ($charset !== \false) {
            return $charset;
        }
        return null;
    }
}

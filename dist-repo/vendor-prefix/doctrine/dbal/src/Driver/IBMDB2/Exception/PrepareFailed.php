<?php

declare (strict_types=1);
namespace Archetype\Vendor\Doctrine\DBAL\Driver\IBMDB2\Exception;

use Archetype\Vendor\Doctrine\DBAL\Driver\AbstractException;
/** @internal */
final class PrepareFailed extends AbstractException
{
    /** @param array{message: string}|null $error */
    public static function new(?array $error): self
    {
        if ($error === null) {
            return new self('Unknown error');
        }
        return new self($error['message']);
    }
}

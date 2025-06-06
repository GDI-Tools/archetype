<?php

namespace Archetype\Vendor\Doctrine\DBAL\Exception;

/**
 * Exception for a deadlock error of a transaction detected in the driver.
 */
class DeadlockException extends ServerException implements RetryableException
{
}

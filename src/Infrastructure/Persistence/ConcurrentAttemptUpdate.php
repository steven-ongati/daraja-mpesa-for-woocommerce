<?php
/**
 * Concurrent payment-attempt update.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Persistence;

use RuntimeException;

/**
 * Signals that another request already changed the loaded attempt.
 */
final class ConcurrentAttemptUpdate extends RuntimeException {
}

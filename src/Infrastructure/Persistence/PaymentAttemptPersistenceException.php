<?php
/**
 * Payment-attempt persistence failure.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Persistence;

use RuntimeException;

/**
 * Provides safe storage errors without exposing SQL or database details.
 */
final class PaymentAttemptPersistenceException extends RuntimeException {
}

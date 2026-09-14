<?php
/**
 * Invalid payment-attempt transition.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Domain;

use LogicException;

/**
 * Prevents out-of-order or contradictory payment mutations.
 */
final class InvalidAttemptTransition extends LogicException {
}

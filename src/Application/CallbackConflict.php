<?php
/**
 * Callback conflict exception.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Application;

use RuntimeException;

/**
 * Indicates callback evidence conflicts with immutable payment intent.
 */
final class CallbackConflict extends RuntimeException {
}

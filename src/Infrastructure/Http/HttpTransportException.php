<?php
/**
 * HTTP transport failure.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Infrastructure\Http;

use RuntimeException;

/**
 * Reports a provider network failure without carrying credentials or payloads.
 */
final class HttpTransportException extends RuntimeException {
}

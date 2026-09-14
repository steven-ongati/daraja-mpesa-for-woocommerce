<?php
/**
 * WordPress payment attempt identifier generator.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

namespace DarajaMpesa\Support;

use DarajaMpesa\Application\AttemptIdGenerator;

/**
 * Generates UUIDs through the WordPress cryptographic random source.
 */
final class WordPressAttemptIdGenerator implements AttemptIdGenerator {
	/**
	 * Generate a UUID.
	 */
	public function generate(): string {
		return wp_generate_uuid4();
	}
}

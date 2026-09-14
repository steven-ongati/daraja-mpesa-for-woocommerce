<?php
/**
 * Minimal WordPress function test stubs.
 *
 * @package DarajaMpesa
 */

declare(strict_types=1);

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Matches WordPress core functions.
// phpcs:disable WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Implements the WordPress test stub.
/**
 * Test-compatible wp_parse_url implementation.
 *
 * @param string   $url       URL to parse.
 * @param int|null $component Optional PHP URL component.
 *
 * @return array<string, int|string>|int|string|null|false
 */
function wp_parse_url( string $url, ?int $component = null ): array|int|string|null|false {
	return null === $component ? parse_url( $url ) : parse_url( $url, $component );
}

/**
 * Test-compatible add_query_arg implementation.
 *
 * @param array<string, string> $parameters Query parameters.
 * @param string                $url        Base URL.
 */
function add_query_arg( array $parameters, string $url ): string {
	$separator = str_contains( $url, '?' ) ? '&' : '?';

	return $url . $separator . http_build_query( $parameters, '', '&', PHP_QUERY_RFC3986 );
}
// phpcs:enable

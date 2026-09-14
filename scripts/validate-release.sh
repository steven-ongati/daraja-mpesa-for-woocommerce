#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
slug="daraja-mpesa-for-woocommerce"
version="$(
	sed -n "s/^ \\* Version:[[:space:]]*\\([^[:space:]]*\\).*/\\1/p" \
		"${root}/daraja-mpesa-for-woocommerce.php"
)"
archive="${root}/build/${slug}-${version}.zip"
contents="${root}/build/archive-contents.txt"
validation="${root}/build/validation"

if [[ ! -f "${archive}" ]]; then
	echo "Release archive does not exist: ${archive}" >&2
	exit 1
fi

unzip -Z1 "${archive}" > "${contents}"

required=(
	"${slug}/daraja-mpesa-for-woocommerce.php"
	"${slug}/uninstall.php"
	"${slug}/readme.txt"
	"${slug}/LICENSE"
	"${slug}/assets/js/checkout-blocks.js"
	"${slug}/assets/js/customer-payment-status.js"
	"${slug}/src/Plugin.php"
)

for file in "${required[@]}"; do
	if ! grep -Fqx "${file}" "${contents}"; then
		echo "Release archive is missing ${file}." >&2
		exit 1
	fi
done

if grep -Ev "^${slug}/" "${contents}" | grep -q .; then
	echo "Release archive contains files outside the plugin directory." >&2
	exit 1
fi

if grep -E \
	"/(tests|vendor|node_modules|scripts|docs|build)/|/(composer|package)(-lock)?\\.json$|/compose\\.yaml$|/Dockerfile$|/\\.env" \
	"${contents}"; then
	echo "Release archive contains development or sensitive files." >&2
	exit 1
fi

rm -rf "${validation}"
mkdir -p "${validation}"
unzip -q "${archive}" -d "${validation}"
find "${validation}/${slug}" -type f -name '*.php' -exec php -l {} \; >/dev/null

expected_version="Version:           ${version}"
if ! grep -Fq "${expected_version}" "${validation}/${slug}/daraja-mpesa-for-woocommerce.php"; then
	echo "Plugin metadata version does not match the archive version." >&2
	exit 1
fi

sha256sum --check "${archive}.sha256"
printf 'Validated %s\n' "${archive}"

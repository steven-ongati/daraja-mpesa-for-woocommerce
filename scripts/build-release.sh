#!/usr/bin/env bash

set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
slug="daraja-mpesa-for-woocommerce"
version="$(
	sed -n "s/^ \\* Version:[[:space:]]*\\([^[:space:]]*\\).*/\\1/p" \
		"${root}/daraja-mpesa-for-woocommerce.php"
)"

if [[ -z "${version}" ]]; then
	echo "Unable to read the plugin version." >&2
	exit 1
fi

build="${root}/build"
stage="${build}/stage/${slug}"
archive="${build}/${slug}-${version}.zip"

rm -rf "${build}"
mkdir -p "${stage}"

install -m 0644 "${root}/daraja-mpesa-for-woocommerce.php" "${stage}/"
install -m 0644 "${root}/uninstall.php" "${stage}/"
install -m 0644 "${root}/readme.txt" "${stage}/"
install -m 0644 "${root}/LICENSE" "${stage}/"
cp -R "${root}/assets" "${stage}/assets"
cp -R "${root}/src" "${stage}/src"

find "${stage}" -type f -name '*.php' -exec php -l {} \; >/dev/null

(
	cd "${build}/stage"
	zip -X -q -r "${archive}" "${slug}"
)

sha256sum "${archive}" > "${archive}.sha256"
printf 'Built %s\n' "${archive}"

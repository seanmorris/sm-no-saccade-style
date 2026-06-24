#!/usr/bin/env sh
set -eu

tag="${1:?Usage: sh scripts/validate-release-tag.sh vX.Y.Z [expected-version]}"
expected_version="${2:-}"

if ! printf '%s' "$tag" | grep -Eq '^v[0-9]+\.[0-9]+\.[0-9]+$'; then
	printf '::error::Release tags must match vX.Y.Z. Got: %s\n' "$tag" >&2
	exit 1
fi

if [ -n "$expected_version" ] && [ "${tag#v}" != "$expected_version" ]; then
	printf '::error::Tag %s does not match package version %s.\n' "$tag" "$expected_version" >&2
	exit 1
fi

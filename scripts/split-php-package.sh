#!/usr/bin/env sh
set -eu

branch="${1:-split/php}"

git branch -D "$branch" >/dev/null 2>&1 || true
git subtree split --prefix=packages/php -b "$branch"

printf 'Created PHP package split branch: %s\n' "$branch"
printf 'Push this branch or tag it from the split branch for Packagist consumption.\n'

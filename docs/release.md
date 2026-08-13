# Release

Publish releases by pushing a version tag that matches `vX.Y.Z`. The tag version must match `packages/js/package.json`.

```sh
version=0.1.3
npm version --workspace sm-no-saccade-style "$version" --no-git-tag-version
git add package-lock.json packages/js/package.json
git commit -m "Release v$version"
git tag "v$version"
git push origin main "v$version"
```

## npm

The `Publish to npm` workflow runs on `v*.*.*` tags. It validates the tag, reuses the shared package verifier, downloads the packed tarball, and publishes it with npm trusted publishing.

Configure the package on npm with this trusted publisher workflow:

```text
.github/workflows/publish-npm.yml
```

Manual fallback:

```sh
npm --workspace sm-no-saccade-style publish
```

## Composer

The `Publish Composer package` workflow runs on the same `v*.*.*` tags. It validates the tag, reuses the shared package verifier, creates a subtree split from `packages/php`, then pushes the split `main` branch and the matching version tag to the Composer package repository.

Add a `COMPOSER_PACKAGE_REPOSITORY` secret containing a push-capable Git URL for the Composer package repository. Packagist should watch that split repository.

Manual fallback:

```sh
npm run release:php:split
git tag -d v0.1.3 >/dev/null 2>&1 || true
git tag v0.1.3 split/php
git push <php-package-remote> split/php:main
git push <php-package-remote> v0.1.3
```

Use a different local split branch when needed:

```sh
sh scripts/split-php-package.sh split/php-v0.1.3
```

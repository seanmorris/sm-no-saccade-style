# Release

Publish releases by pushing a version tag that matches `vX.Y.Z`. The tag version must match `packages/js/package.json`.

## 0.2.0

This release adds configurable line limits, declaration and statement spacing, complete inline or vertical parameter lists, expanded array/object layouts, and aligned closing delimiters. PHP and JavaScript share the layout conventions; the default limit is 100 columns and consumers can select 120. Readability warnings suggest separating long blocks, and PHP warns about documented callbacks that would read better as named locals. Existing public exports, rule names, and runtime requirements remain available.

PHP preserves embedded data after `__halt_compiler();`, including binary bytes and significant whitespace. JavaScript binary and concatenation wrapping no longer adds repeated blank lines, and ternary layout settles before its nested calls expand. Regressions verify unchanged programs and stable repeated formatting at both supported line-limit examples.

Before tagging the reviewed commit, run:

```sh
npm ci
composer install --no-interaction --prefer-dist
npm test
npm run lint
npm run verify:packages
```

The archive verifier installs the npm workspace archive, Git-install archive, and Composer archive in temporary consumers. It checks public entry points, repeated JavaScript/TypeScript autofixes, PHP payload preservation, and PHP CLI exit codes. CI uses the same verifier and publishes the exact npm archive it tested from `build/npm-package/`.

The 0.2.0 workspace version is already prepared. After merging the reviewed changes and checking CI on that commit, validate and push its release tag:

```sh
version=0.2.0
sh scripts/validate-release-tag.sh "v$version" "$(node -p "require('./packages/js/package.json').version")"
git tag "v$version"
git push origin master "v$version"
```

## npm

The `Publish to npm` workflow runs on `v*.*.*` tags. It validates the tag, reuses the shared package verifier, downloads the packed tarball, and publishes it with npm trusted publishing.

Log in to npm and configure the trusted publisher from the repository root:

```sh
npm login
npm run release:npm:trust
npm run release:npm:trust:list
```

The setup command configures package `sm-no-saccade-style` for repository `seanmorris/sm-no-saccade-style`, workflow filename `publish-npm.yml`, no GitHub environment, and the `npm publish` action. npm requires only the workflow filename, not the `.github/workflows/` path.

Manual fallback after the same archive verification:

```sh
npm publish build/npm-package/sm-no-saccade-style-0.2.0.tgz
```

## Composer

The repository-root `composer.json` defines `seanmorris/sm-no-saccade-style`, so the same `v*.*.*` tags publish the PHP package without a subtree split or a second Git repository.

Register `https://github.com/seanmorris/sm-no-saccade-style` with Packagist once and enable its GitHub update hook. Packagist will then read the root manifest and import new versions from the shared release tags.

Validate the Composer package before tagging:

```sh
composer validate --strict
composer install --no-interaction --prefer-dist
composer test
```

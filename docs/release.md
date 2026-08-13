# Release

Publish releases by pushing a version tag that matches `vX.Y.Z`. The tag version must match `packages/js/package.json`.

```sh
version=0.1.4
npm version --workspace sm-no-saccade-style "$version" --no-git-tag-version
git add package-lock.json packages/js/package.json
git commit -m "Release v$version"
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

Manual fallback:

```sh
npm --workspace sm-no-saccade-style publish
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

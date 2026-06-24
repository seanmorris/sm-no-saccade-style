# sm-no-saccade-style

Monorepo for the no-saccade style linters.

This repository publishes two companion packages that enforce the same left-rail formatting style:

- JavaScript and TypeScript: [`sm-no-saccade-style`](./packages/js), an ESLint flat-config plugin.
- PHP: [`seanmorris/sm-no-saccade-style`](./packages/php), a PHP-CS-Fixer custom fixer package.

The shared style contract lives in [`docs/style-contract.md`](./docs/style-contract.md). Package-specific setup and examples live in each package README.

## Development

Install JavaScript dependencies from the repository root:

```sh
npm install
```

Install PHP dependencies from the Composer package root:

```sh
composer --working-dir=packages/php install
```

Run all checks:

```sh
npm run lint
npm test
```

Run package-specific checks:

```sh
npm run lint:js
npm run test:js
composer --working-dir=packages/php lint
composer --working-dir=packages/php test
```

# sm-no-saccade-style

Monorepo for the no-saccade style linters.

## Why saccades matter

When reading code, the eye does not move smoothly across the page. It jumps in quick motions called saccades, then pauses briefly to take in detail.

Those jumps are cheap when the next meaningful symbol is easy to predict and easy to find. They become expensive when punctuation that controls structure is scattered across the far right edge of lines, because the eye has to keep leaving the left rail to confirm where lists continue, where expressions break, and where blocks open or close.

That repeated scanning adds friction. It slows reacquisition after every line break, increases miss risk in dense multiline code, and makes editing harder because structural markers are not where the eye naturally returns.

sm-no-saccade-style is built to reduce that cost. It pulls high-signal syntax back toward the left side of the line so continuation structure is visible earlier, with less hunting and fewer long corrective eye movements.

## Design

Put control symbols where the eye naturally reacquires the next line.

This style favors:

- list separators that lead continued lines
- continuation operators that lead continued lines
- predictable block openings
- delimiter rails that make nested structure visible
- minimal blank-line noise between adjacent structural delimiters
- consistent indentation
- tight structural spacing around control forms
- no trailing whitespace outside string content
- one final trailing newline

The goal is not novelty for its own sake. The goal is to keep structural information near the beginning of each line so readers can scan vertical shape, continuation, and nesting before committing attention to the rest of the expression.

## Packages

This repository publishes two companion packages that enforce the same left-rail formatting style:

- JavaScript and TypeScript: [`sm-no-saccade-style`](./packages/js), an ESLint flat-config plugin.
- PHP: [`seanmorris/sm-no-saccade-style`](./packages/php), a PHP-CS-Fixer custom fixer package.

The shared style contract lives in [`docs/style-contract.md`](./docs/style-contract.md). Package-specific setup and examples live in each package README.

## Development

Install JavaScript dependencies from the repository root:

```sh
npm install
```

Install PHP dependencies from the repository root:

```sh
composer install
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
composer lint
composer test
```

# No-Saccade Style Contract

The JavaScript and PHP packages enforce the same readable layout goals where each language has equivalent syntax.

## Rules

- Multiline list commas lead continued items.
- Final comma-only lines are forbidden in the recommended preset.
- Multiline continuation operators lead the continued line.
- Normal declarations and block forms use Allman opening braces.
- Inline function-like expressions keep opening braces on the same line.
- Multiline control/function heads keep the opening brace on the same line as the closing parenthesis.
- Indentation uses tabs.
- Closing delimiters align with their opening rail.
- Extra blank lines between adjacent structural delimiters are removed.
- Control keywords hug the opening parenthesis.
- Statements are terminated with semicolons where the language uses statement semicolons.
- Semicolons stay at the end of the statement line, not on their own continuation line.
- Trailing whitespace is removed outside string literals.
- Files end with a final newline.

## Language Boundaries

- The JavaScript package applies list rules to arrays, objects, object patterns, and array patterns.
- The PHP package applies list rules to arrays, `array()` forms, `list()` destructuring, destructuring patterns, and vertical function or method calls.
- The PHP package is implemented as PHP-CS-Fixer fixers; consumers use `php-cs-fixer check` and `php-cs-fixer fix`.

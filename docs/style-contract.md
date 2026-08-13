# No-Saccade Style Contract

The JavaScript and PHP packages enforce the same readable layout goals where each language has equivalent syntax.

## Rules

- Multiline list commas lead continued items, and each leading comma is followed by exactly one space.
- Final comma-only lines are forbidden in the recommended preset.
- Compact grouped list rows are allowed when they stay short; over-wide grouped rows are split onto separate continued lines.
- PHP array arrows keep at least one space after `=>`; single spacing before `=>` is accepted, and a multiline list is realigned only when at least one item has more than one space before `=>`.
- JavaScript object colons allow no space before `:` and at least one space after `:`; single spacing after `:` is accepted, and a multiline object is realigned only when at least one item has more than one space after `:`.
- Class property initializer `=` signs keep at least one space before and after `=`; single spacing before `=` is accepted, and a class body is realigned only when at least one initialized property has more than one space before `=`.
- Multiline continuation operators lead the continued line.
- Normal declarations and block forms use Allman opening braces.
- Inline function-like expressions, inline class-like expressions, inline object methods, empty compact bodies, and single-line function, method, and class bodies keep opening braces on the same line.
- Multiline control/function heads keep the opening brace on the same line as the closing parenthesis.
- Indentation uses tabs.
- Closing delimiters align with their opening rail.
- Closing delimiters from different opening rails do not share one line.
- Extra blank lines between adjacent structural delimiters are removed: no blank lines for `open -> open` or `close -> close`, and at most one blank line for `close -> open`.
- Control keywords hug the opening parenthesis.
- PHP dynamic member access braces stay inline.
- Statements are terminated with semicolons where the language uses statement semicolons.
- Semicolons stay at the end of the statement line, not on their own continuation line.
- Trailing whitespace is removed outside string literals.
- Files end with a final newline.

## Language Boundaries

- The JavaScript package applies list rules to arrays, objects, object patterns, and array patterns.
- The PHP package applies list rules to arrays, `array()` forms, `list()` destructuring, destructuring patterns, and vertical function or method calls.
- The PHP package is implemented as PHP-CS-Fixer fixers; consumers use `php-cs-fixer check` and `php-cs-fixer fix`.

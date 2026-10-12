# No-Saccade Style Contract

The JavaScript and PHP packages enforce the same readable layout goals where each language has equivalent syntax.

## Rules

- Multiline list commas lead continued items, and each leading comma is followed by exactly one space.
- Function and method parameters and call arguments use one space after a comma when the next item is on the same line. This includes closures, arrow functions, and constructor calls. Existing newlines and comments are preserved.
- A `for` header uses one space after each semicolon when another clause follows on the same line, including an empty middle clause. No space is added before the closing parenthesis or at the end of a line.
- Final comma-only lines are forbidden in the recommended preset.
- Compact grouped list rows are allowed when they stay short; over-wide grouped rows are split onto separate continued lines.
- PHP array arrows keep at least one space after `=>`; single spacing before `=>` is accepted, and a multiline list is realigned only when at least one item has more than one space before `=>`.
- JavaScript object colons allow no space before `:` and at least one space after `:`; single spacing after `:` is accepted, and a multiline object is realigned only when at least one item has more than one space after `:`.
- Class property initializer `=` signs keep at least one space before and after `=`; single spacing before `=` is accepted, and a class body is realigned only when at least one initialized property has more than one space before `=`.
- Multiline continuation operators lead the continued line.
- Lines target 100 columns, with tabs advancing to four-column tab stops. Both packages wrap at token boundaries and report lines that still exceed the limit. Long literal payloads are exempt by default and are never rewritten.
- Long calls expand at their outer argument list first. Complete arguments get continuation lines; nested calls that fit remain together. Whitespace-only wraps inside a nested argument may be rejoined, without flattening comments, blocks, or multiline literals.
- Within multiline bodies, separate setup from a following control statement, separate a conditional from the following statement, and separate multiline call statements from preceding work. Keep leading comments with their statements. Directly nested control chains and loop-to-return transitions do not require extra blank lines.
- Classes, named functions, and methods have a blank line before their declaration. Put that blank line before attached documentation and attributes or decorators, including the first method in a class. A declaration at the beginning of a JavaScript file does not need a leading empty line.
- Normal declarations and block forms use Allman opening braces.
- A compact braced body is allowed only when the complete construct is on one line, fits the line limit, and its braces are required by the language. Short functions and complete `try/catch/finally` statements may stay compact.
- Optional control braces, including empty `if`, `for`, and `while` bodies, expand to normal blocks. Existing braces are preserved; the formatters do not remove lexical scopes or rewrite dangling `else` relationships.
- A multiline block puts its statements and closing brace on separate lines and indents each statement according to its nesting. Existing unbraced control chains remain supported.
- Inline callbacks and expressions remain attached to their enclosing expression. Assigned functions and JavaScript object methods receive declaration spacing.
- Multiline control/function heads keep the opening brace on the same line as the closing parenthesis.
- Indentation uses tabs.
- Multiline parentheses, braces, and square brackets close on the indentation level of the line where they opened, using a separate closing line when needed. A short nested call can keep its closing parenthesis on its argument line; the outer multiline call closes on its own indentation level.
- Closing delimiters from different opening rails do not share one line.
- Extra blank lines between adjacent structural delimiters are removed: no blank lines for `open -> open` or `close -> close`, and at most one blank line for `close -> open`.
- Control keywords hug the opening parenthesis.
- PHP dynamic member access braces stay inline.
- Statements are terminated with semicolons where the language uses statement semicolons.
- Semicolons stay at the end of the statement line, not on their own continuation line.
- Trailing whitespace is removed outside string literals.
- Files end with a final newline.
- More than 10 consecutive nonblank code lines produce one warning per run, suggesting semantic separation. Comment-only lines and multiline literal contents do not contribute to a run. This check does not insert blank lines automatically.

## Language Boundaries

- The JavaScript package applies list rules to arrays, objects, object patterns, and array patterns.
- The PHP package applies list rules to arrays, `array()` forms, `list()` destructuring, destructuring patterns, and vertical function or method calls.
- The PHP package uses PHP-CS-Fixer for formatting. Run the companion `sm-no-saccade-style` command for remaining line-length errors and semantic-separation warnings. PHP-CS-Fixer itself has no warning severity.

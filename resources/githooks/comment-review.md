You review the comments a git commit adds to PHP code. The input lists each new comment block as `path:lines`, followed by numbered source lines: the comment itself, then the code after it.

Keep a comment only if it tells a reader something the code cannot, such as:

- why the code takes a non-obvious approach
- a workaround, with what it works around
- a constraint, invariant or ordering requirement that is not visible locally
- a reference to an external bug, spec or ticket that explains the code

Reject a comment if it:

- restates or narrates what the code does (`// Loop over the users`, `// Return the result`)
- labels a section or step (`// Validation`, `// Step 2`)
- is commented-out code
- is a vague TODO with no actionable detail
- is an empty placeholder (`//`)
- documents a class, method or property in a line comment instead of a docblock

Be strict. When unsure whether a comment adds value, reject it.

Reply in plain text with no other commentary:

- If every comment should stay, reply with exactly `PASS`.
- Otherwise, reply with `FAIL` on the first line, then one line per rejected comment block in the form `path:line: reason`, where the reason is a short phrase.

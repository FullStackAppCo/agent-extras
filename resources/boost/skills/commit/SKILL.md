---
name: commit
description: Commit staged or working-tree changes as atomic commits with terse single-line messages ending in an issue number when there is an associated issue. Use whenever the user asks to commit work.
---

# Commit

Create git commits following this project's conventions.

## Rules

1. **Atomic commits** — each commit contains one logical change only. If the working tree holds unrelated changes, split them into separate commits, staging each group of related files (or hunks via `git add -p`) individually.
2. **Terse messages** — one short sentence in sentence case, past tense (e.g. `Added qa checks`). No descriptive body or bullet points — an atomic commit is self-explanatory. Don't add a `Co-Authored-By: Claude` trailer; the commit-msg hook strips it (and any `Claude-Session` trailer) anyway.
3. **Issue number at the end, prefixed with `#`** — when the commit is associated with an issue, the subject line ends with the issue number as the final token, always prefixed with a hash (e.g. `#7`), e.g.:

   ```
   Added qa checks #8
   ```

   Never write the number bare (`Added qa checks 8`), with words (`issue 8`, `closes #8`), or anywhere other than the end of the subject line. If the issue number isn't known, check recent `git log` for the issue currently being worked on, or ask the user which issue this relates to. It's OK to omit the issue number if there is no associated issue.

4. **Message shape** — the subject line alone:

   ```
   Added qa checks #8
   ```

## Workflow

1. Run `git status` and `git diff` to see what's changed.
2. Group the changes into logical units. One unit → one commit.
3. For each unit: stage only its files/hunks, then commit with a message following the shape above (terse subject, nothing else).
4. Verify with `git log --oneline` that each subject is terse and ends with `#<issue>` when there is an associated issue.

## Don'ts

- No descriptive bodies, bullet points, or Claude attribution trailers below the subject.
- No lumping unrelated changes into one commit.
- Don't push unless asked.

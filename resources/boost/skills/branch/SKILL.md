---
name: branch
description: Naming convention for git branches. Use whenever creating a branch in this project.
---

# Branch

Branch naming convention for this project.

## Rules

- **Terse, kebab-case names** — e.g. `node20-action-warnings`, `qa-workflow`.
- **No forward slashes** and **no type prefixes** like `fix/`, `feature/`, `chore/`.
- Name describes the change, kept as short as possible while staying meaningful.
- **Issue number goes at the end, and only at the end** — if the work has an associated issue, append its number as the final kebab-case segment, e.g. `node20-action-warnings-42`. Never put it at the start or in the middle, and never prefix it with `issue`, `#`, or similar.

## Examples

| Good | Bad |
|---|---|
| `node20-action-warnings` | `fix/node20-action-warnings` |
| `commit-skill` | `feature/add-commit-skill` |
| `pint-config` | `chore/update-pint-config` |
| `node20-action-warnings-42` | `42-node20-action-warnings` |
| `pint-config-17` | `pint-17-config` |
| `commit-skill-8` | `issue-8-commit-skill` |

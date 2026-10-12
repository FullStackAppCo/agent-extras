---
name: tag
description: Naming convention for git tags. Use whenever creating a tag in this project.
---

# Tag

Tag naming convention for this project.

## Rules

- **`v`-prefixed semantic versions** — a literal lowercase `v` followed by `MAJOR.MINOR.PATCH`, e.g. `v1.4.2`.
- **Bare integer segments** — no leading zeros in any segment.
- **No suffixes** — no pre-release or build metadata like `-beta` or `+build`.
- **Nothing else** — no `release-` style prefixes, no uppercase `V`.

## Examples

| Good | Bad |
|---|---|
| `v1.4.2` | `1.4.2` |
| `v0.1.0` | `V0.1.0` |
| `v10.20.30` | `v1.2` |
| `v2.0.0` | `v2.0.0-beta` |
| `v1.0.1` | `release-1.0.1` |

---
name: update-guidelines
description: Update Laravel Boost AI guidelines and skills to the latest versions. Use when the user wants to refresh or update the Boost guidelines, after running composer update, or when Boost may be out of date.
allowed-tools: Bash(php artisan boost:update*), Bash(php artisan boost:install*)
---

# Update Laravel Boost Guidelines

Updates the Boost-managed AI guidelines and skills to reflect the latest Laravel ecosystem package versions installed in the project.

## When to Use

- After running `composer update` to pick up guideline changes from updated packages
- When Boost guidelines may be out of date with installed package versions
- When new Boost-supported packages have been added to `composer.json`
- When explicitly asked to refresh AI guidelines

## What Gets Updated

`boost:update` regenerates:
- **Agent guideline files** — e.g. `CLAUDE.md` for Claude Code — recompiled from `.ai/guidelines/` and Boost's built-in package guidelines
- **Skills** — installed to `.claude/skills/` from both `.ai/skills/` (custom) and Boost's built-in skills for detected packages

It does **not** touch:
- `.ai/guidelines/` — your custom guideline source files (committed to git)
- `.ai/skills/` — your custom skill source files (committed to git)
- `.claude/settings.json` or `.claude/agents/` — agent subagent definitions

## Workflow

1. Run the update command:

```bash
php artisan boost:update
```

2. Confirm success output: `Boost guidelines and skills updated successfully.`

3. If the command fails with "Please set up Boost with boost:install first", run the full install instead:

```bash
php artisan boost:install --no-interaction
```

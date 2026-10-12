# Agent Extras

Artisan commands that enforce the FSAC git conventions in Laravel projects worked on by coding agents. Each command validates a name or message against the convention and prints a descriptive error that tells the agent (or human) exactly how to fix it, so the conventions hold whether the commit, branch or tag came from a person or from Claude Code.

> ⚠️ This package has not yet reached v1 and may introduce breaking changes between releases. Pin to a specific version.

## Installation

As an internal tool this package is not currently available on Packagist. To install it, add a `vcs` repository entry to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://gitlab.com/fullstackappco/agent-extras"
        }
    ]
}
```

Then require the package:

```bash
composer require --dev fsac/agent-extras "0.1.0"
```

The service provider is registered through Laravel package discovery, so the commands are available from `php artisan` straight away.

## Commands

### `agent-extras:validate-branch [branch]`

Validates a branch name against the branch naming convention: terse kebab-case with no type prefix such as `feature/`, and an optional issue number appended bare as the final segment (e.g. `pint-config-17`). With no argument it validates the current branch. `main` is exempt.

### `agent-extras:validate-tag [tag]`

Validates a tag name against the tag naming convention: a `v`-prefixed semantic version with no leading zeros or suffixes (e.g. `v1.4.2`). With no argument it validates every local tag.

### `agent-extras:format-commit-message <file>`

Takes the path to a commit message file, strips any `Claude-Session:` and `Co-Authored-By: Claude` trailers Claude Code appended, writes the result back, and then validates the message against the commit message convention: a single terse subject in sentence case with no trailing full stop and no descriptive body, optionally ending in a bare `#issue` number (e.g. `Added qa checks #8`). Trailers such as a human `Co-Authored-By` are allowed after a blank line. Messages git writes itself (merges, reverts, fixups) are exempt.

Every command exits non-zero on failure, so they slot into git hooks directly.

## Git hooks

The commands are designed to back a project's `.githooks` directory:

- `commit-msg` runs `agent-extras:format-commit-message` against the message file.
- `pre-push` runs `agent-extras:validate-branch` and `agent-extras:validate-tag` for each ref being pushed, or for the current branch and all local tags when run manually from a terminal.

Enable the hooks with `git config core.hooksPath .githooks`, typically from a `post-install-cmd` composer script.

## License

[MIT](LICENSE.md)

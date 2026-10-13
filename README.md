# Agent Extras

Git hooks that enforce the FSAC git conventions in Laravel projects worked on by coding agents, backed by Artisan commands. Each hook validates a branch, tag or commit message against the convention and prints a descriptive error that tells the agent (or human) exactly how to fix it, so the conventions hold whether the change came from a person or from Claude Code.

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

The service provider is registered through Laravel package discovery, so the Artisan commands the hooks rely on are available straight away.

## Git hooks

The package's job is to back a project's git hooks. Publish them into your project's `.githooks` directory:

```bash
php artisan vendor:publish --tag=githooks
```

To publish only this package's hooks, scope the command to its provider:

```bash
php artisan vendor:publish --provider="FullStackAppCo\AgentExtras\ServiceProvider" --tag=githooks
```

- `commit-msg` runs `agent-extras:format-commit-message` against the message file.
- `pre-push` runs `agent-extras:validate-branch` and `agent-extras:validate-tag` for each ref being pushed, or for the current branch and all local tags when run manually from a terminal.
- `lib.sh` is a shared helper the hooks source to find `artisan` via Herd, Sail or the host PHP.

Each hook prints a descriptive error and exits non-zero when a name or message breaks the convention. Enable the hooks with `git config core.hooksPath .githooks`, typically from a composer script so every clone picks them up and stays current:

```json
{
    "scripts": {
        "post-install-cmd": [
            "@php artisan vendor:publish --tag=githooks --force",
            "git config core.hooksPath .githooks || true"
        ]
    }
}
```

The published hooks are kept executable, so no `chmod` step is needed.

## Commands

> ℹ️ The commands exist to serve the git hooks above and are not intended for general standalone use. Their arguments and output are shaped around what the hooks need and may change between releases without notice.

### `agent-extras:validate-branch [branch]`

Validates a branch name against the branch naming convention: terse kebab-case with no type prefix such as `feature/`, and an optional issue number appended bare as the final segment (e.g. `pint-config-17`). With no argument it validates the current branch. `main` is exempt.

### `agent-extras:validate-tag [tag]`

Validates a tag name against the tag naming convention: a `v`-prefixed semantic version with no leading zeros or suffixes (e.g. `v1.4.2`). With no argument it validates every local tag.

### `agent-extras:format-commit-message <file>`

Takes the path to a commit message file, strips any `Claude-Session:` and `Co-Authored-By: Claude` trailers Claude Code appended, writes the result back, and then validates the message against the commit message convention: a single terse subject in sentence case with no trailing full stop and no descriptive body, optionally ending in a bare `#issue` number (e.g. `Added qa checks #8`). Trailers such as a human `Co-Authored-By` are allowed after a blank line. Messages git writes itself (merges, reverts, fixups) are exempt.

## Development

`composer install` enables the repository's own hooks (`core.hooksPath`) and symlinks `.claude/skills` to `resources/boost/skills`, so the skills are maintained in one place. The package has no `artisan`, so the hooks run the commands through the Testbench CLI, which boots the service provider listed in `testbench.yaml`:

```bash
vendor/bin/testbench agent-extras:validate-branch
```

## License

[MIT](LICENSE.md)

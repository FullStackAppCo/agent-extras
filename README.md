# Agent Extras

Git hooks that enforce the FSAC git conventions in Laravel projects worked on by coding agents, backed by Artisan commands. Each hook validates a branch, tag or commit message against the convention and prints a descriptive error that tells the agent (or human) exactly how to fix it, so the conventions hold whether the change came from a person or from Claude Code.

It also installs the FSAC quality tooling: the `test`, `analyze`, `format` and `refactor` composer scripts along with the packages and configuration they rely on, and enables the git hooks, keeping them current through `composer install` and `composer update`.

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
composer require --dev fsac/agent-extras "0.1.4"
```

The service provider is registered through Laravel package discovery, so the Artisan commands the hooks rely on are available straight away.

### Laravel Zero

Laravel Zero ignores package discovery, so register the service provider yourself in `config/app.php`:

```php
'providers' => [
    App\Providers\AppServiceProvider::class,
    FullStackAppCo\AgentExtras\ServiceProvider::class,
],
```

Then run the commands through the application binary in place of `artisan`, e.g. `php application agent-extras:install`.

## Quality tooling

Run the install command to add the composer scripts, set up their tools and enable the git hooks:

```bash
php artisan agent-extras:install
```

It makes the following changes to the project:

- Adds the `test`, `analyze`, `format` and `refactor` composer scripts, which run PHPUnit, PHPStan (via Larastan), Pint and Rector respectively. Scripts the project already defines are left alone.
- Adds the `vcs` repository entry for `fsac/rector-extras`, the FSAC Rector rules, which is not available on Packagist.
- Requires `phpunit/phpunit`, `larastan/larastan`, `laravel/pint`, `rector/rector` and `fsac/rector-extras` as dev dependencies, skipping any already present.
- Publishes a `phpstan.neon` and a `rector.php` covering the project's directories: `app`, `bootstrap`, `config`, `database`, `routes` and `tests` in a Laravel application, otherwise `app` (Laravel Zero) or `src` (a package) and `tests`. Existing files are left alone. Pint and PHPUnit use the configuration a Laravel application already ships with.
- Copies the FSAC Claude Code skills (`branch`, `commit`, `release` and `tag`) into `.claude/skills` when the project is not a Laravel application, where Laravel Boost is not available to install them, and appends `@php artisan vendor:publish --tag=skills --force` to `post-update-cmd` so they are republished on every `composer update` (through the Laravel Zero binary where there is one). The install leaves existing skills alone, but the republish overwrites them, as it does the git hooks.
- Publishes the git hooks into `.githooks` and points git's `core.hooksPath` at them.
- Appends the `post-install-cmd` and `post-update-cmd` entries described under [Git hooks](#git-hooks), which enable the hooks on every `composer install` and republish them on every `composer update`. Entries the events already list are left alone.

Pass `--force` to overwrite existing scripts, configuration files and skills, and `--composer` with the path to a Composer binary if `composer` is not on the `PATH`.

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
- `lib.sh` is a shared helper the hooks source to run the commands via Herd, Sail or the host PHP. It runs them through `artisan`, or, in a Laravel Zero app, through the binary `composer.json` lists under `bin` (`application` unless the app was renamed).

Each hook prints a descriptive error and exits non-zero when a name or message breaks the convention. Commit the published `.githooks` directory and enable it with `git config core.hooksPath .githooks`, typically from composer scripts: `post-install-cmd` enables the committed hooks in every new clone, and `post-update-cmd` republishes them when the package is updated so they stay current. `agent-extras:install` publishes and enables the hooks and adds these entries for you, calling the Laravel Zero binary in place of `artisan` where there is one:

```json
{
    "scripts": {
        "post-install-cmd": [
            "git config core.hooksPath .githooks || true"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=githooks --force"
        ]
    }
}
```

The published hooks are kept executable, so no `chmod` step is needed.

## Commands

> ℹ️ Apart from `agent-extras:install`, the commands exist to serve the git hooks above and are not intended for general standalone use. Their arguments and output are shaped around what the hooks need and may change between releases without notice.

### `agent-extras:install`

Adds the `test`, `analyze`, `format` and `refactor` composer scripts, installs and configures their dependencies publishes and enables the git hooks and appends the `post-install-cmd` and `post-update-cmd` entries that keep them enabled and current, as described under [Quality tooling](#quality-tooling). Accepts `--force` and `--composer=<path>`.

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

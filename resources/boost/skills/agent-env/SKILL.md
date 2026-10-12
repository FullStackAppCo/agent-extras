---
name: agent-env
description: Create, run commands in, and tear down isolated Laravel Sail development environments (git worktree + its own Sail stack of PHP 8.5 and Postgres 18 on unique localhost ports) so several agents or issues can be worked on in parallel without clashing. Use when starting work on an issue that should not disturb the main checkout, when asked for a fresh/parallel/agent/Sail/Docker environment, or when cleaning one up.
allowed-tools: Bash(bin/agent-env *), Bash(git worktree list*)
---

# Agent Environment

`bin/agent-env` builds a complete, self-contained copy of the project that runs on its own Laravel Sail stack, side by side with the main checkout and any other environments. The agent stays on the host and edits the worktree; PHP, Postgres and Vite run in Sail's containers. Nothing outside the worktree and its containers is touched, and nothing is shared between environments except git objects and the `sail-8.5/app` image.

## What an environment is

For a name like `agent-env-12` in the `website` checkout:

| Piece | Value |
|---|---|
| Directory | `.worktrees/agent-env-12` (a git worktree inside the main repo, gitignored) |
| Branch | `agent-env-12` — created from `origin/main` (or `--base <ref>`), or checked out if it already exists |
| Compose project | `website-agent-env-12` — the committed `compose.yaml`: Sail's `laravel.test` (PHP 8.5) and `pgsql` (Postgres 18) |
| Site | `http://localhost:<APP_PORT>` — first free port from 8100 |
| Vite / HMR | `localhost:<VITE_PORT>` — first free from 5174; `npm run dev` runs in the background in the app container |
| Postgres | `localhost:<FORWARD_DB_PORT>` for host tools (first free from 5433); inside the stack the app uses `pgsql:5432`, db `laravel`, user `sail` — migrated and seeded. The test suite uses Sail's separate `testing` database via `.env.testing`, so `RefreshDatabase` never wipes the seeded data |
| Git identity | Inherited from the main repo's local config and verified — commits are authored exactly as they would be from the main checkout |
| Local config | `.env` (copied and re-pointed: ports, `APP_URL`, `DB_*`, `COMPOSE_PROJECT_NAME`) and `.env.testing`, plus any of `.mcp.json`, `boost.json`, `CLAUDE.md`, `auth.json`, `.claude/settings.local.json`, `.ai/mcp` that exist |

Every published port binds to `127.0.0.1` only. `vendor/` and `node_modules/` are installed into the worktree by the container.

## Commands

Run from anywhere inside the main repo or any environment:

```bash
bin/agent-env create <name> [--base <ref>]      # build and start (the first ever run builds the Sail image: several minutes)
bin/agent-env list                              # all environments and what's running
bin/agent-env info <name>                       # ports, branch, identity, compose project
bin/agent-env up|down <name>                    # start / stop containers (state is kept)
bin/agent-env sail <name> <command...>          # any Sail command, run in the environment
bin/agent-env destroy <name> [--delete-branch]  # containers + volumes + worktree
```

`<name>` follows the `branch` skill: terse kebab-case, issue number last (`agent-env-12`). It becomes the branch name.

Anything that needs PHP, Node or the database goes through `sail`:

```bash
bin/agent-env sail agent-env-12 composer test
bin/agent-env sail agent-env-12 composer coverage
bin/agent-env sail agent-env-12 artisan migrate:fresh --seed
bin/agent-env sail agent-env-12 artisan tinker --execute='...'
```

Inside the worktree, `vendor/bin/sail …` works directly too — its `.env` carries `COMPOSE_PROJECT_NAME` and the ports.

## Main checkout

Environments are built from the main checkout's `.env` and its `vendor/` (which holds the Sail runtime the image is built from), so it needs a one-off setup. On a machine with Docker but no host PHP:

```bash
cp .env.example .env
# Set DB_CONNECTION=pgsql, DB_HOST=pgsql, DB_PORT=5432, DB_DATABASE=laravel,
# DB_USERNAME=sail, DB_PASSWORD=password and SESSION_SECURE_COOKIE=false.

# Laravel loads .env.testing when APP_ENV=testing (phpunit.xml sets it), so the
# suite gets its own database and never wipes the seeded site data. Both files
# are gitignored; `bin/agent-env create` writes them for each environment.
cp .env .env.testing
sed -i 's|^DB_DATABASE=.*|DB_DATABASE=testing|' .env.testing

# Bootstrap vendor/ without host PHP (scripts need PHP 8.5, so they run next).
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd)":/var/www/html -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs --no-scripts

vendor/bin/sail up -d
vendor/bin/sail composer install
vendor/bin/sail artisan key:generate
vendor/bin/sail artisan migrate --seed
vendor/bin/sail npm ci
vendor/bin/sail npm run build
```

The asset build matters: without `public/build/manifest.json` every test that
renders a page fails with `ViteManifestNotFoundException`. `bin/agent-env
create` does this for you; only the main checkout needs it by hand.

With the stack up, the git hooks run through Sail automatically (`.githooks/lib.sh`).

## Workflow

1. Confirm identity with the `detect-user` skill (the environment inherits it).
2. `bin/agent-env create <name>` and read the summary it prints.
3. `cd` into the printed path and do all work there: edit files on the host, run tests/artisan via `bin/agent-env sail`, commit with the `commit` skill (git runs on the host, as usual).
4. Open the merge request from the environment; the branch pushes over the same remote as the main repo.
5. When the work is merged, `bin/agent-env destroy <name> --delete-branch`.

## Don'ts

- Don't run migrations, seeders, or tests against the main checkout while an environment exists for the task — that's what the environment is for.
- Don't run `herd php`/`herd composer` for tests or artisan inside an environment: its `.env` points at the `pgsql` container, which the host can't resolve. Use `bin/agent-env sail`.
- Don't run `npm install` on the host inside an environment — `node_modules` there is installed by the container.
- Don't edit `git config`, the remote, or `.env` credentials inside an environment to "fix" identity or connection issues — `create` verifies the identity and fails loudly if the main repo's is missing; surface the problem instead.
- Don't `destroy` an environment with uncommitted work (the script refuses) or one you didn't create, without checking first.

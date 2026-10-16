---
name: release
description: Cut a release by bumping the Semver version in composer.json and the README install example, committing, and creating a release tag. Use whenever the user asks to release, tag, or bump the version.
---

# Release

Cut a new release of this package.

## Changelog format

`CHANGELOG.md` follows the format of [Laravel's changelog](https://github.com/laravel/framework/blob/12.x/CHANGELOG.md): newest release first, each heading linking to the forge's compare view from the previous tag, followed by the release date and one bullet per change:

```markdown
# Release Notes

## [Unreleased](https://gitlab.com/<group>/<project>/-/compare/v0.2.0...main)

## [v0.2.0](https://gitlab.com/<group>/<project>/-/compare/v0.1.0...v0.2.0) - 2026-10-16

* Added the configuration publish tag
* Fixed the hook permissions on publish
```

- Compare URLs come from the `origin` URL: `https://gitlab.com/<group>/<project>/-/compare/<from>...<to>` on GitLab, `https://github.com/<owner>/<repo>/compare/<from>...<to>` on GitHub.
- The `Unreleased` heading always sits at the top and compares the latest tag with the default branch. It has no bullets; entries go straight under the release they ship in.
- The first release has no previous tag, so its heading links to the tag itself (`/-/tags/v0.1.0` on GitLab, `/releases/tag/v0.1.0` on GitHub).
- Bullets are the subjects of the commits since the last tag, minus `Released …` commits, folding the steps of one change (such as a refactor made for a feature) into a single bullet. Keep each in the commit's past-tense sentence case and keep any `#<issue>` suffix. Where a change came in through a merged PR or MR, end its bullet with `by @<author> in <PR/MR URL>`, as Laravel does.
- If `CHANGELOG.md` doesn't exist, create it with the `# Release Notes` title and the `Unreleased` heading.

## Semver policy

Versions follow [Semver](https://semver.org) (`MAJOR.MINOR.PATCH`).

While the package is **pre-1.0** (`0.y.z`):

- **Breaking change** → bump **MINOR** (`0.1.0` → `0.2.0`)
- **New feature or fix** → bump **PATCH** (`0.1.0` → `0.1.1`)

From **1.0.0** onwards:

- **Breaking change** → bump **MAJOR**
- **Backwards-compatible feature** → bump **MINOR**
- **Backwards-compatible fix** → bump **PATCH**

To choose the bump, review every commit since the last release tag (`git log <last-tag>..HEAD --oneline`, or all commits if no tag exists yet) and classify the most significant change. A single breaking change makes the whole release breaking. If it's unclear whether a change is breaking, ask the user.

## Tag format

Tags are the version prefixed with `v`, e.g. `v0.2.0`. Annotated tags only.

## Workflow

1. **Preflight** — must be on the default branch, working tree clean, and up to date with the remote (`git fetch origin` then compare). Abort and tell the user if not.
2. **CI green** — the CI pipeline for the current `HEAD` must have passed. Check it with the forge's CLI, determined from the `origin` URL: `gh run list --branch <default> --commit $(git rev-parse HEAD)` on GitHub, or `glab ci list --sha $(git rev-parse HEAD)` on GitLab. If it failed, hasn't run, or is still in progress, abort and tell the user — never release on red or unverified CI.
3. **Determine the new version** — find the current version in `composer.json`, review commits since the last tag, and apply the Semver policy above. Confirm the chosen version with the user before proceeding.
4. **Bump versions** — update the `version` field in `composer.json` and the pinned version in the README install example so all three (composer.json, README, tag) agree.
5. **Changelog** — add the release's entry to `CHANGELOG.md` under the `Unreleased` heading, dated today, and point `Unreleased` at the new tag, as described under [Changelog format](#changelog-format).
6. **Commit** — follow the commit skill; message `Released <version>`, e.g. `Released 0.2.0`. The version bumps and the changelog entry go in this one commit.
7. **Tag** — `git tag -a v<version> -m "v<version>"` on that commit.
8. **Push** — `git push origin <default> v<version>`. The package is installed as a `vcs` repository, so the release isn't usable until the tag is pushed.

## Don'ts

- No release from a dirty tree or a branch other than the default branch.
- No release when CI is red, pending, or missing for the release commit.
- No lightweight tags.
- Don't skip the README version pin — consumers copy the install example verbatim.
- Don't skip the changelog entry, or edit the entries of releases already tagged.
- Don't retag or move an existing tag; if a release is broken, cut a new patch release.

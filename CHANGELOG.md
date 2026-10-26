# Release Notes

## [Unreleased](https://gitlab.com/fullstackappco/agent-extras/-/compare/v0.1.7...main)

## [v0.1.7](https://gitlab.com/fullstackappco/agent-extras/-/compare/v0.1.6...v0.1.7) - 2026-10-26

* Replaced a real email address in the test data with an example one by @jobyh in https://gitlab.com/fullstackappco/agent-extras/-/merge_requests/2
* Bumped rector-extras to 0.1.5 and added the test prefix rule to the stub and Rector config by @jobyh in https://gitlab.com/fullstackappco/agent-extras/-/merge_requests/3

## [v0.1.6](https://gitlab.com/fullstackappco/agent-extras/-/compare/v0.1.5...v0.1.6) - 2026-10-16

* Added a minimum code coverage check to CI by @jobyh in https://gitlab.com/fullstackappco/agent-extras/-/merge_requests/1

## [v0.1.5](https://gitlab.com/fullstackappco/agent-extras/-/compare/v0.1.4...v0.1.5) - 2026-10-16

* Published the configuration stubs with a config tag
* Added a changelog entry to the release skill
* Added the changelog

## [v0.1.4](https://gitlab.com/fullstackappco/agent-extras/-/compare/v0.1.3...v0.1.4) - 2026-10-15

* Configured the stub paths for the project type in the install command
* Published the skills with a skills tag
* Installed the skills when the project is not a Laravel application

## [v0.1.3](https://gitlab.com/fullstackappco/agent-extras/-/compare/v0.1.2...v0.1.3) - 2026-10-15

* Ran the commands through the Laravel Zero binary
* Added CI jobs that install the package into Laravel and Laravel Zero
* Registered validation when the host does not provide it
* Documented registering the provider in Laravel Zero

## [v0.1.2](https://gitlab.com/fullstackappco/agent-extras/-/compare/v0.1.1...v0.1.2) - 2026-10-15

* Enabled the git hooks in the install command
* Required the illuminate components instead of the framework

## [v0.1.1](https://gitlab.com/fullstackappco/agent-extras/-/compare/v0.1.0...v0.1.1) - 2026-10-15

* Added the rector-extras repository before requiring dependencies

## [v0.1.0](https://gitlab.com/fullstackappco/agent-extras/-/tags/v0.1.0) - 2026-10-15

* Published the git hooks
* Added the release skill to the boost skills
* Added the install command
* Added the git hook entries to post-install-cmd in the install command
* Restored PHP 8.4 support

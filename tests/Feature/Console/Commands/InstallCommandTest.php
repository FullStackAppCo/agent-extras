<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use FullStackAppCo\AgentExtras\Composer\JsonIo;
use Illuminate\Process\FakeProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstallCommandTest extends TestCase
{
    protected string $project;

    /**
     * Where the git hooks publish to, which is fixed to the Testbench
     * skeleton when the service provider boots.
     */
    protected string $hooks;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->hooks = $this->app->basePath('.githooks');

        $this->project = sys_get_temp_dir().'/agent-extras-'.Str::random();

        File::makeDirectory($this->project);

        $this->app->setBasePath($this->project);

        Process::fake();
    }

    #[Override]
    protected function tearDown(): void
    {
        File::deleteDirectory($this->project);
        File::deleteDirectory($this->hooks);

        parent::tearDown();
    }

    #[Test]
    public function it_adds_the_composer_scripts(): void
    {
        $this->writeComposer([
            'scripts' => [
                'post-autoload-dump' => ['@php artisan package:discover --ansi'],
            ],
        ]);

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Added the 'test' composer script.")
            ->expectsOutputToContain('Agent extras installed.')
            ->assertSuccessful();

        $this->assertSame([
            'post-autoload-dump' => ['@php artisan package:discover --ansi'],
            'analyze' => ['@php vendor/bin/phpstan analyse'],
            'format' => ['@php vendor/bin/pint'],
            'refactor' => ['@php vendor/bin/rector process'],
            'test' => ['@php vendor/bin/phpunit -d memory_limit=2G'],
            'post-install-cmd' => ['git config core.hooksPath .githooks || true'],
            'post-update-cmd' => ['@php artisan vendor:publish --tag=githooks --force'],
        ], $this->readComposer('scripts'));
    }

    #[Test]
    public function it_appends_the_git_hook_entries_to_existing_post_install_and_post_update_scripts(): void
    {
        $this->writeComposer(['scripts' => [
            'post-install-cmd' => ['@php artisan storage:link'],
            'post-update-cmd' => ['@php artisan boost:update --ansi'],
        ]]);

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Added the git hook entries to the 'post-install-cmd' composer script.")
            ->expectsOutputToContain("Added the git hook entries to the 'post-update-cmd' composer script.")
            ->assertSuccessful();

        $this->assertSame([
            '@php artisan storage:link',
            'git config core.hooksPath .githooks || true',
        ], $this->readComposer('scripts.post-install-cmd'));

        $this->assertSame([
            '@php artisan boost:update --ansi',
            '@php artisan vendor:publish --tag=githooks --force',
        ], $this->readComposer('scripts.post-update-cmd'));
    }

    #[Test]
    public function it_appends_the_git_hook_entries_to_single_post_install_and_post_update_scripts(): void
    {
        $this->writeComposer(['scripts' => [
            'post-install-cmd' => '@php artisan storage:link',
            'post-update-cmd' => '@php artisan boost:update --ansi',
        ]]);

        $this->artisan('agent-extras:install')->assertSuccessful();

        $this->assertSame([
            '@php artisan storage:link',
            'git config core.hooksPath .githooks || true',
        ], $this->readComposer('scripts.post-install-cmd'));

        $this->assertSame([
            '@php artisan boost:update --ansi',
            '@php artisan vendor:publish --tag=githooks --force',
        ], $this->readComposer('scripts.post-update-cmd'));
    }

    #[Test]
    public function it_keeps_post_install_and_post_update_scripts_which_already_have_the_git_hook_entries(): void
    {
        $this->writeComposer(['scripts' => [
            'post-install-cmd' => ['git config core.hooksPath .githooks || true'],
            'post-update-cmd' => ['@php artisan vendor:publish --tag=githooks --force'],
        ]]);

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Skipped the git hook entries already in the 'post-install-cmd' composer script.")
            ->expectsOutputToContain("Skipped the git hook entries already in the 'post-update-cmd' composer script.")
            ->assertSuccessful();

        $this->assertSame(['git config core.hooksPath .githooks || true'], $this->readComposer('scripts.post-install-cmd'));
        $this->assertSame(['@php artisan vendor:publish --tag=githooks --force'], $this->readComposer('scripts.post-update-cmd'));
    }

    #[Test]
    public function it_publishes_the_git_hooks_through_the_composer_bin_when_there_is_no_artisan(): void
    {
        $this->writeComposer(['bin' => ['my-app']]);
        File::put("{$this->project}/my-app", '<?php');

        $this->artisan('agent-extras:install')->assertSuccessful();

        $this->assertSame(
            ['@php my-app vendor:publish --tag=githooks --force'],
            $this->readComposer('scripts.post-update-cmd'),
        );
    }

    #[Test]
    public function it_publishes_the_git_hooks_through_artisan_when_the_project_has_one(): void
    {
        $this->writeComposer(['bin' => ['my-app']]);
        File::put("{$this->project}/artisan", '<?php');
        File::put("{$this->project}/my-app", '<?php');

        $this->artisan('agent-extras:install')->assertSuccessful();

        $this->assertSame(
            ['@php artisan vendor:publish --tag=githooks --force'],
            $this->readComposer('scripts.post-update-cmd'),
        );
    }

    #[Test]
    public function it_publishes_the_git_hooks(): void
    {
        $this->writeComposer();

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain('Published the git hooks.')
            ->assertSuccessful();

        $this->assertFileEquals(__DIR__.'/../../../../resources/githooks/pre-push', "{$this->hooks}/pre-push");
    }

    #[Test]
    public function it_sets_the_git_hooks_path(): void
    {
        $this->writeComposer();

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Set git's core.hooksPath to '.githooks'.")
            ->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'git', 'config', 'core.hooksPath', '.githooks',
        ] && $process->path === $this->project);
    }

    #[Test]
    public function it_warns_when_the_git_hooks_path_cannot_be_set(): void
    {
        Process::fake(fn (PendingProcess $process): FakeProcessResult => Process::result(
            exitCode: $process->command[0] === 'git' ? 128 : 0,
        ));

        $this->writeComposer();

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Unable to set git's core.hooksPath to '.githooks' (is the project a git repository?).")
            ->assertSuccessful();
    }

    #[Test]
    public function it_keeps_existing_composer_scripts(): void
    {
        $this->writeComposer(['scripts' => ['test' => ['@php artisan test']]]);

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Skipped the existing 'test' composer script (use --force to overwrite).")
            ->assertSuccessful();

        $scripts = $this->readComposer('scripts');

        $this->assertSame(['@php artisan test'], $scripts['test']);
        $this->assertSame(['@php vendor/bin/pint'], $scripts['format']);
    }

    #[Test]
    public function it_overwrites_existing_composer_scripts_when_forced(): void
    {
        $this->writeComposer(['scripts' => ['test' => ['@php artisan test']]]);

        $this->artisan('agent-extras:install', ['--force' => true])
            ->expectsOutputToContain("Added the 'test' composer script.")
            ->assertSuccessful();

        $this->assertSame(['@php vendor/bin/phpunit -d memory_limit=2G'], $this->readComposer('scripts.test'));
    }

    #[Test]
    public function it_adds_the_rector_extras_repository(): void
    {
        $this->writeComposer();

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain('Added the rector-extras repository.')
            ->assertSuccessful();

        $this->assertSame(
            [['name' => 'rector-extras', 'type' => 'vcs', 'url' => 'https://gitlab.com/fullstackappco/rector-extras.git']],
            $this->readComposer('repositories'),
        );
    }

    #[Test]
    public function it_adds_the_rector_extras_repository_before_requiring_the_dependencies(): void
    {
        $this->writeComposer();

        Process::fake(function (): FakeProcessResult {
            $this->assertSame(
                [['name' => 'rector-extras', 'type' => 'vcs', 'url' => 'https://gitlab.com/fullstackappco/rector-extras.git']],
                $this->readComposer('repositories'),
            );

            return Process::result();
        });

        $this->artisan('agent-extras:install')->assertSuccessful();

        Process::assertRanTimes(fn (PendingProcess $process): bool => $process->command[1] === 'require', 1);
    }

    #[Test]
    public function it_keeps_an_existing_rector_extras_repository(): void
    {
        $this->writeComposer(['repositories' => [
            ['type' => 'vcs', 'url' => 'https://gitlab.com/fullstackappco/agent-extras.git'],
            ['type' => 'vcs', 'url' => 'https://gitlab.com/fullstackappco/rector-extras'],
        ]]);

        $this->artisan('agent-extras:install')
            ->doesntExpectOutputToContain('Added the rector-extras repository.')
            ->assertSuccessful();

        $this->assertCount(2, $this->readComposer('repositories'));
    }

    #[Test]
    public function it_requires_the_missing_dev_dependencies(): void
    {
        $this->writeComposer([
            'require-dev' => ['laravel/pint' => '^1.24', 'phpunit/phpunit' => '^11.5.3'],
        ]);

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain('Requiring fsac/rector-extras:0.1.4, larastan/larastan and rector/rector.')
            ->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [
            'composer', 'require', '--dev', 'fsac/rector-extras:0.1.4', 'larastan/larastan', 'rector/rector',
        ] && $process->path === $this->project);
    }

    #[Test]
    public function it_requires_nothing_when_every_dependency_is_present(): void
    {
        $this->writeComposer([
            'require' => ['phpunit/phpunit' => '^11.5.3'],
            'require-dev' => [
                'fsac/rector-extras' => '0.1.4',
                'larastan/larastan' => '^3.0',
                'laravel/pint' => '^1.24',
                'rector/rector' => '^2.6',
            ],
        ]);

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain('The dependencies are already required.')
            ->assertSuccessful();

        Process::assertDidntRun(fn (PendingProcess $process): bool => $process->command[1] === 'require');
    }

    #[Test]
    public function it_uses_the_given_composer_binary(): void
    {
        $this->writeComposer();

        $this->artisan('agent-extras:install', ['--composer' => '/usr/local/bin/composer.phar'])->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => array_slice($process->command, 1, 3) === [
            '/usr/local/bin/composer.phar', 'require', '--dev',
        ]);
    }

    #[Test]
    public function it_fails_when_the_dependencies_cannot_be_required(): void
    {
        Process::fake(['*' => Process::result('', exitCode: 1)]);

        $this->writeComposer();

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain('Unable to require the dependencies.')
            ->assertFailed();
    }

    #[Test]
    public function it_publishes_the_configuration_files(): void
    {
        $this->writeComposer();

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Published 'phpstan.neon'.")
            ->expectsOutputToContain("Published 'rector.php'.")
            ->assertSuccessful();

        foreach (['phpstan.neon', 'rector.php'] as $file) {
            $this->assertFileEquals(__DIR__."/../../../../stubs/{$file}", "{$this->project}/{$file}");
        }
    }

    #[Test]
    public function it_keeps_existing_configuration_files(): void
    {
        $this->writeComposer();
        File::put("{$this->project}/rector.php", '<?php return [];');

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Skipped the existing 'rector.php' (use --force to overwrite).")
            ->expectsOutputToContain("Published 'phpstan.neon'.")
            ->assertSuccessful();

        $this->assertStringEqualsFile("{$this->project}/rector.php", '<?php return [];');
    }

    #[Test]
    public function it_overwrites_existing_configuration_files_when_forced(): void
    {
        $this->writeComposer();
        File::put("{$this->project}/rector.php", '<?php return [];');

        $this->artisan('agent-extras:install', ['--force' => true])
            ->expectsOutputToContain("Published 'rector.php'.")
            ->assertSuccessful();

        $this->assertFileEquals(__DIR__.'/../../../../stubs/rector.php', "{$this->project}/rector.php");
    }

    #[Test]
    public function it_fails_when_there_is_no_composer_json(): void
    {
        $this->artisan('agent-extras:install')
            ->expectsOutputToContain('Unable to read')
            ->assertFailed();

        Process::assertNothingRan();
        $this->assertFileDoesNotExist("{$this->project}/phpstan.neon");
    }

    /**
     * @param  array<string, mixed>  $composer
     */
    protected function writeComposer(array $composer = []): void
    {
        new JsonIo("{$this->project}/composer.json")->write(['name' => 'acme/app', ...$composer]);
    }

    /**
     * Read the project's composer.json, optionally extracting the value at
     * the given dot-notation path.
     */
    protected function readComposer(?string $path = null): mixed
    {
        $composer = new JsonIo("{$this->project}/composer.json")->read();

        return $path === null ? $composer : data_get($composer, $path);
    }
}

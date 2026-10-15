<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

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

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->project = sys_get_temp_dir().'/agent-extras-'.Str::random();

        File::makeDirectory($this->project);

        $this->app->setBasePath($this->project);

        Process::fake();
    }

    #[Override]
    protected function tearDown(): void
    {
        File::deleteDirectory($this->project);

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
            'post-install-cmd' => [
                '@php artisan vendor:publish --tag=githooks --force',
                'git config core.hooksPath .githooks || true',
            ],
        ], $this->readComposer()['scripts']);
    }

    #[Test]
    public function it_appends_the_git_hook_entries_to_an_existing_post_install_script(): void
    {
        $this->writeComposer(['scripts' => [
            'post-install-cmd' => ['@php artisan storage:link', 'git config core.hooksPath .githooks || true'],
        ]]);

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Added the git hook entries to the 'post-install-cmd' composer script.")
            ->assertSuccessful();

        $this->assertSame([
            '@php artisan storage:link',
            'git config core.hooksPath .githooks || true',
            '@php artisan vendor:publish --tag=githooks --force',
        ], $this->readComposer()['scripts']['post-install-cmd']);
    }

    #[Test]
    public function it_appends_the_git_hook_entries_to_a_single_post_install_script(): void
    {
        $this->writeComposer(['scripts' => ['post-install-cmd' => '@php artisan storage:link']]);

        $this->artisan('agent-extras:install')->assertSuccessful();

        $this->assertSame([
            '@php artisan storage:link',
            '@php artisan vendor:publish --tag=githooks --force',
            'git config core.hooksPath .githooks || true',
        ], $this->readComposer()['scripts']['post-install-cmd']);
    }

    #[Test]
    public function it_keeps_a_post_install_script_which_already_has_the_git_hook_entries(): void
    {
        $this->writeComposer(['scripts' => ['post-install-cmd' => [
            '@php artisan vendor:publish --tag=githooks --force',
            'git config core.hooksPath .githooks || true',
        ]]]);

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Skipped the git hook entries already in the 'post-install-cmd' composer script.")
            ->assertSuccessful();

        $this->assertSame([
            '@php artisan vendor:publish --tag=githooks --force',
            'git config core.hooksPath .githooks || true',
        ], $this->readComposer()['scripts']['post-install-cmd']);
    }

    #[Test]
    public function it_keeps_existing_composer_scripts(): void
    {
        $this->writeComposer(['scripts' => ['test' => ['@php artisan test']]]);

        $this->artisan('agent-extras:install')
            ->expectsOutputToContain("Skipped the existing 'test' composer script (use --force to overwrite).")
            ->assertSuccessful();

        $scripts = $this->readComposer()['scripts'];

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

        $this->assertSame(['@php vendor/bin/phpunit -d memory_limit=2G'], $this->readComposer()['scripts']['test']);
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
            $this->readComposer()['repositories'],
        );
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

        $this->assertCount(2, $this->readComposer()['repositories']);
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

        Process::assertNothingRan();
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
        File::put("{$this->project}/composer.json", json_encode(['name' => 'acme/app', ...$composer], JSON_PRETTY_PRINT));
    }

    /**
     * @return array<string, mixed>
     */
    protected function readComposer(): array
    {
        return json_decode(File::get("{$this->project}/composer.json"), true, flags: JSON_THROW_ON_ERROR);
    }
}

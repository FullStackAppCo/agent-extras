<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Console\Commands;

use FullStackAppCo\AgentExtras\Composer\JsonIo;
use FullStackAppCo\AgentExtras\Composer\PackageManager;
use FullStackAppCo\AgentExtras\Composer\RepositoryManager;
use FullStackAppCo\AgentExtras\Composer\ScriptManager;
use FullStackAppCo\AgentExtras\Exceptions\ScriptAlreadyRegistered;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Override;

class InstallCommand extends Command
{
    /**
     * @var array<string, list<string>>
     */
    protected const array SCRIPTS = [
        'analyze' => ['@php vendor/bin/phpstan analyse'],
        'format' => ['@php vendor/bin/pint'],
        'refactor' => ['@php vendor/bin/rector process'],
        'test' => ['@php vendor/bin/phpunit -d memory_limit=2G'],
    ];

    /**
     * @var list<string>
     */
    protected const array POST_INSTALL_SCRIPTS = [
        '@php artisan vendor:publish --tag=githooks --force',
        'git config core.hooksPath .githooks || true',
    ];

    /**
     * @var array<string, string>
     */
    protected const array PACKAGES = [
        'fsac/rector-extras' => '0.1.4',
        'larastan/larastan' => '',
        'laravel/pint' => '',
        'phpunit/phpunit' => '',
        'rector/rector' => '',
    ];

    /**
     * @var list<string>
     */
    protected const array STUBS = ['phpstan.neon', 'rector.php'];

    #[Override]
    protected $signature = 'agent-extras:install
        {--composer=global : Absolute path to the Composer binary which should be used to install packages}
        {--force : Overwrite existing composer scripts and configuration files}';

    #[Override]
    protected $description = 'Add the test, analyze, format and refactor composer scripts, installing and configuring their dependencies, and enable the git hooks.';

    public function handle(): int
    {
        $path = $this->laravel->basePath('composer.json');

        if (! File::isFile($path)) {
            $this->error("Unable to read '{$path}'.");

            return self::FAILURE;
        }

        $io = new JsonIo($path);
        $packages = new PackageManager($this->option('composer'), $io->read())
            ->output(function (string $type, string $output): void {
                $this->output->write($output);
            });

        if (! $packages->require(...self::PACKAGES)) {
            $this->error('Unable to require the dependencies.');

            return self::FAILURE;
        }

        $io->read()
            |> $this->addRepositories(...)
            |> $this->addScripts(...)
            |> $this->addHookScripts(...)
            |> $io->write(...);

        $this->publishConfiguration();

        $this->info('Agent extras installed.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $composer
     * @return array<string, mixed>
     */
    protected function addRepositories(array $composer): array
    {
        $manager = new RepositoryManager($composer);
        $url = 'https://gitlab.com/fullstackappco/rector-extras.git';

        if ($manager->has($url)) {
            return $composer;
        }

        $this->info('Added the rector-extras repository.');

        return $manager->add('rector-extras', $url)->toArray();
    }

    /**
     * @param  array<string, mixed>  $composer
     * @return array<string, mixed>
     */
    protected function addScripts(array $composer): array
    {
        $manager = new ScriptManager($composer);
        $force = $this->option('force');

        Collection::make(self::SCRIPTS)->each(function (array $scripts, string $name) use ($manager, $force): void {
            try {
                $manager->add($name, $scripts, $force);

                $this->info("Added the '{$name}' composer script.");
            } catch (ScriptAlreadyRegistered) {
                $this->warn("Skipped the existing '{$name}' composer script (use --force to overwrite).");
            }
        });

        return $manager->toArray();
    }

    /**
     * @param  array<string, mixed>  $composer
     * @return array<string, mixed>
     */
    protected function addHookScripts(array $composer): array
    {
        $manager = new ScriptManager($composer);
        $appended = $manager->append('post-install-cmd', self::POST_INSTALL_SCRIPTS);

        if ($appended === []) {
            $this->warn("Skipped the git hook entries already in the 'post-install-cmd' composer script.");
        } else {
            $this->info("Added the git hook entries to the 'post-install-cmd' composer script.");
        }

        return $manager->toArray();
    }

    /**
     * Copy the configuration stubs into the project, keeping any files the
     * project already has unless --force is given.
     */
    protected function publishConfiguration(): void
    {
        foreach (self::STUBS as $stub) {
            $target = $this->laravel->basePath($stub);

            if (File::exists($target) && ! $this->option('force')) {
                $this->warn("Skipped the existing '{$stub}' (use --force to overwrite).");

                continue;
            }

            File::copy(__DIR__."/../../../stubs/{$stub}", $target);

            $this->info("Published '{$stub}'.");
        }
    }
}

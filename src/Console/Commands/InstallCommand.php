<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Console\Commands;

use FullStackAppCo\AgentExtras\Composer\JsonIo;
use FullStackAppCo\AgentExtras\Composer\PackageManager;
use FullStackAppCo\AgentExtras\Composer\RepositoryManager;
use FullStackAppCo\AgentExtras\Composer\ScriptManager;
use FullStackAppCo\AgentExtras\Exceptions\ScriptAlreadyRegistered;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

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

    protected $signature = 'agent-extras:install
        {--composer=global : Absolute path to the Composer binary which should be used to install packages}
        {--force : Overwrite existing composer scripts and configuration files}';

    protected $description = 'Add the test, analyze, format and refactor composer scripts, installing and configuring their dependencies, and enable the git hooks.';

    public function handle(): int
    {
        $path = $this->laravel->basePath('composer.json');

        if (! File::isFile($path)) {
            $this->error("Unable to read '{$path}'.");

            return self::FAILURE;
        }

        $io = new JsonIo($path);

        // Composer can only resolve fsac/rector-extras once its repository is registered.
        $io->write($this->addRepositories($io->read()));

        $packages = new PackageManager($this->option('composer'), $io->read())
            ->output(function (string $type, string $output): void {
                $this->output->write($output);
            });

        if (! $packages->require(...self::PACKAGES)) {
            $this->error('Unable to require the dependencies.');

            return self::FAILURE;
        }

        $composer = $this->addScripts($io->read());
        $composer = $this->addHookScripts($composer);

        $io->write($composer);

        $this->publishConfiguration();
        $this->enableHooks();

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

        Collection::make([
            'post-install-cmd' => self::POST_INSTALL_SCRIPTS,
            'post-update-cmd' => ["@php {$this->entryPoint($composer)} vendor:publish --tag=githooks --force"],
        ])->each(function (array $scripts, string $event) use ($manager): void {
            if ($manager->append($event, $scripts) === []) {
                $this->warn("Skipped the git hook entries already in the '{$event}' composer script.");
            } else {
                $this->info("Added the git hook entries to the '{$event}' composer script.");
            }
        });

        return $manager->toArray();
    }

    /**
     * The project's console entry point, found the same way as the git hooks
     * find it: `artisan` in an application, otherwise the binary composer.json
     * lists under `bin`, which is how Laravel Zero names it.
     *
     * @param  array<string, mixed>  $composer
     */
    protected function entryPoint(array $composer): string
    {
        $bin = Arr::first(Arr::wrap($composer['bin'] ?? []));

        if (! File::isFile($this->laravel->basePath('artisan')) && is_string($bin) && File::isFile($this->laravel->basePath($bin))) {
            return $bin;
        }

        return 'artisan';
    }

    /**
     * Publish the git hooks and point git at them, as the composer scripts
     * would, so the hooks work without another composer install or update.
     */
    protected function enableHooks(): void
    {
        $this->callSilently('vendor:publish', ['--tag' => 'githooks', '--force' => true]);

        $this->info('Published the git hooks.');

        $result = Process::path($this->laravel->basePath())->run(['git', 'config', 'core.hooksPath', '.githooks']);

        if ($result->successful()) {
            $this->info("Set git's core.hooksPath to '.githooks'.");
        } else {
            $this->warn("Unable to set git's core.hooksPath to '.githooks' (is the project a git repository?).");
        }
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

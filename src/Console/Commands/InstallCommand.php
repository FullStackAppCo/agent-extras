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
use Illuminate\Support\Str;

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
     * The directories the configuration covers in a Laravel application.
     *
     * @var list<string>
     */
    protected const array APPLICATION_PATHS = ['app', 'bootstrap', 'config', 'database', 'routes', 'tests'];

    /**
     * The directories within those paths which hold generated files.
     *
     * @var list<string>
     */
    protected const array APPLICATION_SKIP = ['bootstrap/cache'];

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

        if (! $this->isLaravel() && is_string($bin) && File::isFile($this->laravel->basePath($bin))) {
            return $bin;
        }

        return 'artisan';
    }

    /**
     * Whether the project is a Laravel application, as opposed to a Laravel
     * Zero project or a package, which have no `artisan`.
     */
    protected function isLaravel(): bool
    {
        return File::isFile($this->laravel->basePath('artisan'));
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
     * Publish the configuration stubs into the project, keeping any files the
     * project already has unless --force is given.
     */
    protected function publishConfiguration(): void
    {
        [$paths, $skip] = $this->configurationPaths();

        Collection::make([
            'phpstan.neon' => [
                'paths' => Arr::map($paths, fn (string $path): string => "        - {$path}/"),
            ],
            'rector.php' => [
                'paths' => Arr::map($paths, fn (string $path): string => "        __DIR__.'/{$path}',"),
                'skip' => Arr::map($skip, fn (string $path): string => "        __DIR__.'/{$path}',"),
            ],
        ])->each(function (array $placeholders, string $stub): void {
            $target = $this->laravel->basePath($stub);

            if (File::exists($target) && ! $this->option('force')) {
                $this->warn("Skipped the existing '{$stub}' (use --force to overwrite).");

                return;
            }

            File::put($target, $this->renderStub($stub, $placeholders));

            $this->info("Published '{$stub}'.");
        });
    }

    /**
     * The directories the configuration covers, and those it skips within
     * them. Laravel Zero keeps its code in `app` and a package in `src`.
     *
     * @return array{list<string>, list<string>}
     */
    protected function configurationPaths(): array
    {
        if ($this->isLaravel()) {
            return [self::APPLICATION_PATHS, self::APPLICATION_SKIP];
        }

        $source = File::isDirectory($this->laravel->basePath('app')) ? 'app' : 'src';

        return [[$source, 'tests'], []];
    }

    /**
     * @param  array<string, list<string>>  $placeholders
     */
    protected function renderStub(string $stub, array $placeholders): string
    {
        $contents = File::get(__DIR__."/../../../stubs/{$stub}.stub");

        foreach ($placeholders as $name => $lines) {
            $contents = Str::replace(
                "{{ {$name} }}\n",
                $lines === [] ? '' : implode("\n", $lines)."\n",
                $contents,
            );
        }

        return $contents;
    }
}

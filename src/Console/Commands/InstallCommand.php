<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Console\Commands;

use FullStackAppCo\AgentExtras\Composer\JsonIo;
use FullStackAppCo\AgentExtras\Composer\PackageManager;
use FullStackAppCo\AgentExtras\Composer\RepositoryManager;
use FullStackAppCo\AgentExtras\Composer\ScriptManager;
use FullStackAppCo\AgentExtras\ConfigurationStubs;
use FullStackAppCo\AgentExtras\Exceptions\ScriptAlreadyRegistered;
use FullStackAppCo\AgentExtras\ServiceProvider;
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
        'check-psr4' => ['@composer dump-autoload --strict-psr --optimize --dry-run'],
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
        'fsac/rector-extras' => '0.1.5',
        'larastan/larastan' => '',
        'laravel/pint' => '',
        'phpunit/phpunit' => '',
        'rector/rector' => '',
    ];

    protected $signature = 'agent-extras:install
        {--composer=global : Absolute path to the Composer binary which should be used to install packages}
        {--force : Overwrite existing composer scripts, configuration files and skills}';

    protected $description = 'Add the test, analyze, check-psr4, format and refactor composer scripts, installing and configuring their dependencies, and enable the git hooks.';

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
        $composer = $this->addSkillScripts($composer);

        $io->write($composer);

        $this->publishConfiguration();
        $this->publishSkills();
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
     * Republish the skills on every composer update so they stay current,
     * unless the project is a Laravel application, where Laravel Boost
     * looks after them.
     *
     * @param  array<string, mixed>  $composer
     * @return array<string, mixed>
     */
    protected function addSkillScripts(array $composer): array
    {
        if ($this->isLaravel()) {
            return $composer;
        }

        $manager = new ScriptManager($composer);
        $tag = ServiceProvider::SKILLS_TAG;

        if ($manager->append('post-update-cmd', ["@php {$this->entryPoint($composer)} vendor:publish --tag={$tag} --force"]) === []) {
            $this->warn("Skipped the skill entries already in the 'post-update-cmd' composer script.");
        } else {
            $this->info("Added the skill entries to the 'post-update-cmd' composer script.");
        }

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
        $stubs = new ConfigurationStubs($this->laravel->basePath());

        Collection::make(ConfigurationStubs::FILES)->each(function (string $file) use ($stubs): void {
            $target = $this->laravel->basePath($file);

            if (File::exists($target) && ! $this->option('force')) {
                $this->warn("Skipped the existing '{$file}' (use --force to overwrite).");

                return;
            }

            File::put($target, $stubs->render($file));

            $this->info("Published '{$file}'.");
        });
    }

    /**
     * Copy the skills into the project's `.claude/skills` directory when the
     * project is not a Laravel application, as only there does Laravel Boost
     * install them. Keeps any skills the project already has unless --force
     * is given.
     */
    protected function publishSkills(): void
    {
        if ($this->isLaravel()) {
            return;
        }

        // The published paths resolve against the base path at boot, so only
        // the sources are taken from the provider.
        Collection::make(ServiceProvider::pathsToPublish(ServiceProvider::class, ServiceProvider::SKILLS_TAG))
            ->keys()
            ->each(function (string $source): void {
                $skill = basename($source);
                $target = $this->laravel->basePath(".claude/skills/{$skill}");

                if (File::exists($target) && ! $this->option('force')) {
                    $this->warn("Skipped the existing '{$skill}' skill (use --force to overwrite).");

                    return;
                }

                File::deleteDirectory($target);
                File::copyDirectory($source, $target);

                $this->info("Published the '{$skill}' skill.");
            });
    }
}

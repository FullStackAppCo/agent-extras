<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras;

use FullStackAppCo\AgentExtras\Console\Commands\Git\FormatCommitMessageCommand;
use FullStackAppCo\AgentExtras\Console\Commands\Git\ValidateBranchCommand;
use FullStackAppCo\AgentExtras\Console\Commands\Git\ValidateTagCommand;
use FullStackAppCo\AgentExtras\Console\Commands\InstallCommand;
use Illuminate\Foundation\Events\VendorTagPublished;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    protected const string HOOKS_TAG = 'githooks';

    public const string SKILLS_TAG = 'skills';

    /**
     * The skills which only make sense alongside Laravel Boost.
     *
     * @var list<string>
     */
    protected const array BOOST_SKILLS = ['update-guidelines'];

    /**
     * The git commands validate through the Validator facade, which Laravel
     * Zero leaves unbound, so register validation (and the translator it
     * depends on) unless the host already provides them.
     */
    public function register(): void
    {
        if (! $this->app->bound('translator')) {
            $this->app->register(TranslationServiceProvider::class);
        }

        if (! $this->app->bound('validator')) {
            $this->app->register(ValidationServiceProvider::class);
        }
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            FormatCommitMessageCommand::class,
            InstallCommand::class,
            ValidateBranchCommand::class,
            ValidateTagCommand::class,
        ]);

        $this->publishes([
            __DIR__.'/../resources/githooks' => $this->app->basePath('.githooks'),
        ], self::HOOKS_TAG);

        $this->publishes($this->skillPaths(), self::SKILLS_TAG);

        Event::listen(VendorTagPublished::class, $this->restoreHookPermissions(...));
    }

    /**
     * Each skill's directory mapped to its place in the project's Claude Code
     * skills. Laravel Boost installs the skills in a Laravel application, so
     * these are for projects without it, which have no use for the skills
     * that drive Boost itself.
     *
     * @return array<string, string>
     */
    protected function skillPaths(): array
    {
        return Collection::make(File::directories(__DIR__.'/../resources/boost/skills'))
            ->reject(fn (string $source): bool => in_array(basename($source), self::BOOST_SKILLS, true))
            ->mapWithKeys(fn (string $source): array => [
                $source => $this->app->basePath('.claude/skills/'.basename($source)),
            ])
            ->all();
    }

    /**
     * Publishing copies file contents but not modes, and git ignores hooks
     * that are not executable, so mirror each source file's mode onto its
     * published copy.
     */
    protected function restoreHookPermissions(VendorTagPublished $event): void
    {
        if ($event->tag !== self::HOOKS_TAG) {
            return;
        }

        foreach ($event->paths as $from => $to) {
            foreach (File::files($from) as $file) {
                $published = $to.DIRECTORY_SEPARATOR.$file->getFilename();

                if (File::isFile($published)) {
                    File::chmod($published, $file->getPerms() & 0777);
                }
            }
        }
    }
}

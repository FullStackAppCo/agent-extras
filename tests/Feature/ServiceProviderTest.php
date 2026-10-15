<?php

declare(strict_types=1);

namespace Tests\Feature;

use FullStackAppCo\AgentExtras\ServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        File::deleteDirectory($this->app->basePath('.githooks'));
        File::deleteDirectory($this->app->basePath('.claude'));

        parent::tearDown();
    }

    #[Test]
    public function it_registers_validation_when_the_host_does_not_provide_it(): void
    {
        // A bare application with only config and a filesystem, like Laravel Zero.
        $app = new Application($this->app->basePath());
        $app->instance('config', new Repository);
        $app->instance('files', new Filesystem);

        new ServiceProvider($app)->register();

        $this->assertTrue($app->make('validator')->make(['tag' => 'v1.0.0'], ['tag' => 'regex:/^v/'])->passes());
    }

    #[Test]
    public function it_keeps_the_validator_the_host_provides(): void
    {
        $validator = $this->app->make('validator');

        $this->app->register(ServiceProvider::class, force: true);

        $this->assertSame($validator, $this->app->make('validator'));
    }

    #[Test]
    public function it_publishes_the_git_hooks(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'githooks'])->assertSuccessful();

        foreach (['commit-msg', 'pre-push', 'lib.sh'] as $hook) {
            $this->assertFileExists($this->app->basePath(".githooks/{$hook}"));
            $this->assertFileEquals(__DIR__."/../../resources/githooks/{$hook}", $this->app->basePath(".githooks/{$hook}"));
        }
    }

    #[Test]
    public function it_preserves_the_file_permissions_of_the_published_git_hooks(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'githooks'])->assertSuccessful();

        foreach (File::files(__DIR__.'/../../resources/githooks') as $source) {
            $published = $this->app->basePath(".githooks/{$source->getFilename()}");

            $this->assertSame(
                Str::padLeft(decoct($source->getPerms() & 0777), 4, '0'),
                Str::padLeft(decoct(fileperms($published) & 0777), 4, '0'),
                "The published {$source->getFilename()} does not have the same permissions as its source.",
            );
        }

        $this->assertTrue(is_executable($this->app->basePath('.githooks/commit-msg')));
        $this->assertTrue(is_executable($this->app->basePath('.githooks/pre-push')));
        $this->assertFalse(is_executable($this->app->basePath('.githooks/lib.sh')));
    }

    #[Test]
    public function it_publishes_the_git_hooks_when_scoped_to_the_provider(): void
    {
        $this->artisan('vendor:publish', ['--provider' => ServiceProvider::class, '--tag' => 'githooks'])
            ->assertSuccessful();

        $this->assertFileExists($this->app->basePath('.githooks/commit-msg'));
        $this->assertFileExists($this->app->basePath('.githooks/pre-push'));
        $this->assertFileExists($this->app->basePath('.githooks/lib.sh'));
    }

    #[Test]
    public function it_publishes_the_skills(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'skills'])->assertSuccessful();

        foreach (['branch', 'commit', 'release', 'tag'] as $skill) {
            $this->assertFileEquals(
                __DIR__."/../../resources/boost/skills/{$skill}/SKILL.md",
                $this->app->basePath(".claude/skills/{$skill}/SKILL.md"),
            );
        }
    }

    #[Test]
    public function it_does_not_publish_the_laravel_boost_skills(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'skills'])->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->app->basePath('.claude/skills/update-guidelines'));
    }
}

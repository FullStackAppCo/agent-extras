<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Git;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ValidateTagCommandTest extends TestCase
{
    #[Test]
    #[TestWith(['v1.4.2'])]
    #[TestWith(['v0.1.0'])]
    #[TestWith(['v10.20.30'])]
    #[TestWith(['v2.0.0'])]
    public function it_accepts_tag_names_following_the_convention(string $tag): void
    {
        $this->artisan('agent-extras:validate-tag', ['tag' => $tag])
            ->expectsOutputToContain("Tag name '{$tag}' is valid.")
            ->assertSuccessful();
    }

    #[Test]
    #[TestWith(['v1.2'])]
    #[TestWith(['v1.2.3.4'])]
    #[TestWith(['V1.2.3'])]
    #[TestWith(['v1.02.3'])]
    #[TestWith(['v1.2.3-beta'])]
    #[TestWith(['release-1.2.3'])]
    public function it_rejects_names_that_are_not_v_prefixed_semantic_versions(string $tag): void
    {
        $this->artisan('agent-extras:validate-tag', ['tag' => $tag])
            ->expectsOutputToContain("is not a 'v'-prefixed semantic version")
            ->expectsOutputToContain('See the tag skill')
            ->assertFailed();
    }

    #[Test]
    public function it_rejects_a_missing_v_prefix(): void
    {
        $this->artisan('agent-extras:validate-tag', ['tag' => '1.4.2'])
            ->expectsOutputToContain("is missing its 'v' prefix")
            ->assertFailed();
    }

    #[Test]
    public function it_validates_every_local_tag_by_default(): void
    {
        Process::fake([
            '*git*tag*--list*' => Process::result("v1.0.0\nrelease-1.1.0\n"),
        ]);

        $this->artisan('agent-extras:validate-tag')
            ->expectsOutputToContain("Tag name 'v1.0.0' is valid.")
            ->expectsOutputToContain("is not a 'v'-prefixed semantic version")
            ->assertFailed();
    }

    #[Test]
    public function it_succeeds_when_every_local_tag_follows_the_convention(): void
    {
        Process::fake([
            '*git*tag*--list*' => Process::result("v1.0.0\nv1.1.0\n"),
        ]);

        $this->artisan('agent-extras:validate-tag')
            ->expectsOutputToContain("Tag name 'v1.0.0' is valid.")
            ->expectsOutputToContain("Tag name 'v1.1.0' is valid.")
            ->assertSuccessful();
    }

    #[Test]
    public function it_succeeds_when_there_are_no_local_tags(): void
    {
        Process::fake([
            '*git*tag*--list*' => Process::result(''),
        ]);

        $this->artisan('agent-extras:validate-tag')
            ->expectsOutputToContain('No tags to validate.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_fails_when_the_local_tags_cannot_be_listed(): void
    {
        Process::fake([
            '*git*tag*--list*' => Process::result('', exitCode: 1),
        ]);

        $this->artisan('agent-extras:validate-tag')
            ->expectsOutputToContain('Unable to list the local tags.')
            ->assertFailed();
    }
}

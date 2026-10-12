<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Git;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ValidateBranchCommandTest extends TestCase
{
    #[Test]
    #[TestWith(['node20-action-warnings'])]
    #[TestWith(['commit-skill'])]
    #[TestWith(['pint-config-17'])]
    #[TestWith(['node20-action-warnings-42'])]
    public function it_accepts_branch_names_following_the_convention(string $branch): void
    {
        $this->artisan('agent-extras:validate-branch', ['branch' => $branch])
            ->expectsOutputToContain("Branch name '{$branch}' is valid.")
            ->assertSuccessful();
    }

    #[Test]
    public function it_exempts_main(): void
    {
        $this->artisan('agent-extras:validate-branch', ['branch' => 'main'])->assertSuccessful();
    }

    #[Test]
    #[TestWith(['feature/csp-drop-bunny'])]
    #[TestWith(['camelCaseBranchName'])]
    #[TestWith(['Bad_Name'])]
    #[TestWith(['double--dash'])]
    #[TestWith(['trailing-'])]
    #[TestWith(['-leading'])]
    public function it_rejects_names_that_are_not_terse_kebab_case(string $branch): void
    {
        $this->artisan('agent-extras:validate-branch', ['branch' => $branch])
            ->expectsOutputToContain('is not terse kebab-case')
            ->expectsOutputToContain('See the branch skill')
            ->assertFailed();
    }

    #[Test]
    #[TestWith(['42'])]
    #[TestWith(['42-node20-action-warnings'])]
    #[TestWith(['pint-17-config'])]
    #[TestWith(['commit-skill-issue-8'])]
    public function it_rejects_a_misplaced_issue_number(string $branch): void
    {
        $this->artisan('agent-extras:validate-branch', ['branch' => $branch])
            ->expectsOutputToContain('misplaces the issue number')
            ->assertFailed();
    }

    #[Test]
    public function it_validates_the_current_git_branch_by_default(): void
    {
        Process::fake([
            '*symbolic-ref*' => Process::result("feature/csp-drop-bunny\n"),
        ]);

        $this->artisan('agent-extras:validate-branch')
            ->expectsOutputToContain('is not terse kebab-case')
            ->assertFailed();
    }

    #[Test]
    public function it_fails_when_the_current_branch_cannot_be_determined(): void
    {
        Process::fake([
            '*symbolic-ref*' => Process::result('', exitCode: 1),
        ]);

        $this->artisan('agent-extras:validate-branch')
            ->expectsOutputToContain('Unable to determine the current branch')
            ->assertFailed();
    }
}

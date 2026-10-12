<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Console\Commands\Git;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Override;

class ValidateBranchCommand extends Command
{
    /**
     * Branches exempt from the naming convention.
     *
     * @var list<string>
     */
    protected const array EXEMPT = ['main'];

    #[Override]
    protected $signature = 'agent-extras:validate-branch
        {branch? : The branch name to validate (defaults to the current git branch)}';

    #[Override]
    protected $description = 'Validate a git branch name against the branch naming convention.';

    public function handle(): int
    {
        $branch = $this->argument('branch') ?? $this->currentBranch();

        if ($branch === null) {
            $this->error('Unable to determine the current branch (detached HEAD?).');

            return self::FAILURE;
        }

        try {
            Validator::make(
                ['branch' => $branch],
                [
                    'branch' => [
                        Rule::excludeIf(in_array($branch, self::EXEMPT, true)),
                        'required',
                        'string',
                        'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                        'not_regex:/^\d+(-|$)|-\d+-|-issue-\d+$/',
                    ],
                ],
                [
                    'branch.regex' => "'{$branch}' is not terse kebab-case — use lowercase letters and digits in segments separated by single hyphens, with no type prefix like 'feature/' (e.g. 'pint-config').",
                    'branch.not_regex' => "'{$branch}' misplaces the issue number — append it bare as the final segment, and only there (e.g. 'pint-config-17', never 'issue-17' or '17-pint-config').",
                ],
            )->validate();
        } catch (ValidationException $exception) {
            $this->error("Branch name check failed: {$exception->validator->errors()->first('branch')}");
            $this->error('See the branch skill for the naming convention.');

            return self::FAILURE;
        }

        $this->info("Branch name '{$branch}' is valid.");

        return self::SUCCESS;
    }

    protected function currentBranch(): ?string
    {
        $result = Process::run(['git', 'symbolic-ref', '--quiet', '--short', 'HEAD']);

        if ($result->failed()) {
            return null;
        }

        $branch = Str::trim($result->output());

        return $branch === '' ? null : $branch;
    }
}

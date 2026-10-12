<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Console\Commands\Git;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Override;

class ValidateTagCommand extends Command
{
    #[Override]
    protected $signature = 'agent-extras:validate-tag
        {tag? : The tag name to validate (defaults to validating every local tag)}';

    #[Override]
    protected $description = 'Validate a git tag name against the tag naming convention.';

    public function handle(): int
    {
        $tag = $this->argument('tag');

        $tags = $tag === null ? $this->localTags() : [$tag];

        if ($tags === null) {
            $this->error('Unable to list the local tags.');

            return self::FAILURE;
        }

        if ($tags === []) {
            $this->info('No tags to validate.');

            return self::SUCCESS;
        }

        $failed = false;

        foreach ($tags as $tag) {
            try {
                $this->validateTag($tag);

                $this->info("Tag name '{$tag}' is valid.");
            } catch (ValidationException $exception) {
                $this->error("Tag name check failed: {$exception->validator->errors()->first('tag')}");

                $failed = true;
            }
        }

        if ($failed) {
            $this->error('See the tag skill for the naming convention.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function validateTag(string $tag): void
    {
        Validator::make(
            ['tag' => $tag],
            [
                'tag' => [
                    'required',
                    'string',
                    'not_regex:/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/',
                    'regex:/^v(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/',
                ],
            ],
            [
                'tag.not_regex' => "'{$tag}' is missing its 'v' prefix — version tags start with a literal lowercase 'v' (e.g. 'v1.4.2').",
                'tag.regex' => "'{$tag}' is not a 'v'-prefixed semantic version — use 'v' followed by MAJOR.MINOR.PATCH with no leading zeros or suffixes (e.g. 'v1.4.2').",
            ],
        )->validate();
    }

    /**
     * Every tag in the local repository, or null when the list cannot be read.
     *
     * @return list<string>|null
     */
    protected function localTags(): ?array
    {
        $result = Process::run(['git', 'tag', '--list']);

        if ($result->failed()) {
            return null;
        }

        return array_values(array_filter(
            explode("\n", $result->output()),
            static fn (string $tag): bool => Str::trim($tag) !== '',
        ));
    }
}

<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Console\Commands\Git;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Override;

class FormatCommitMessageCommand extends Command
{
    /**
     * Subjects exempt from the convention — messages git writes itself.
     */
    protected const string EXEMPT = '/^(?:Merge|Revert)\b|^(?:fixup|squash|amend)!/';

    /**
     * Everything below the scissors line (git commit --verbose) is discarded.
     */
    protected const string SCISSORS = '# ------------------------ >8 ------------------------';

    /**
     * Trailer lines Claude Code appends that must not reach the repository:
     * the session link and Claude's own Co-Authored-By attribution.
     */
    protected const string CLAUDE_TRAILER = '/^(?:Claude-Session:|Co-Authored-By: Claude\b)[^\r\n]*(?:\r\n|\n|$)/mi';

    #[Override]
    protected $signature = 'agent-extras:format-commit-message
        {file : Path to the file holding the commit message}';

    #[Override]
    protected $description = 'Strip the Claude trailers from a commit message and validate it against the commit message convention.';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! File::isFile($file)) {
            $this->error("Unable to read commit message file '{$file}'.");

            return self::FAILURE;
        }

        $contents = $this->stripClaudeTrailers($file);

        [$subject, $rest] = $this->parse($contents);

        if (preg_match(self::EXEMPT, $subject) === 1) {
            $this->info('Commit message is exempt from the convention (git-generated).');

            return self::SUCCESS;
        }

        try {
            Validator::make(
                ['subject' => $subject, 'rest' => $rest],
                [
                    'subject' => [
                        'required',
                        'string',
                        'max:72',
                        'regex:/^[A-Z].*[^.\s]$/',
                        'not_regex:/#\d+\s+\S|\b(?:clos(?:e|es|ed)|fix(?:es|ed)?|resolv(?:e|es|ed)|issue)\s+#?\d+$|\s\d+$/i',
                    ],
                    'rest' => [$this->trailersOnly(...)],
                ],
                [
                    'subject.required' => 'the message is empty.',
                    'subject.max' => 'the subject exceeds 72 characters — keep it to one terse sentence.',
                    'subject.regex' => 'the subject must be a terse sentence in sentence case with no trailing full stop (e.g. \'Added qa checks\').',
                    'subject.not_regex' => 'the subject misplaces the issue number — end with the bare hash-prefixed number as the final token (e.g. \'Added qa checks #8\', never \'closes #8\' or a bare \'8\').',
                ],
            )->validate();
        } catch (ValidationException $exception) {
            foreach ($exception->validator->errors()->all() as $error) {
                $this->error("Commit message check failed: {$error}");
            }

            $this->error('See the commit skill for the message convention.');

            return self::FAILURE;
        }

        $this->info('Commit message is valid.');

        return self::SUCCESS;
    }

    /**
     * Remove any Claude-Session and Claude Co-Authored-By trailer lines from
     * the file, leaving other trailers intact, and return the resulting
     * contents. Trailing blank lines left behind are trimmed.
     */
    protected function stripClaudeTrailers(string $file): string
    {
        $contents = File::get($file);
        $stripped = (string) preg_replace(self::CLAUDE_TRAILER, '', $contents);

        if ($stripped === $contents) {
            return $contents;
        }

        $stripped = Str::rtrim($stripped)."\n";

        File::put($file, $stripped);
        $this->info('Stripped the Claude trailers.');

        return $stripped;
    }

    /**
     * Reduce the message the way git's cleanup does — drop the verbose diff
     * below the scissors line, comment lines, and surrounding blank lines —
     * then split it into the subject and the remaining lines.
     *
     * @return array{string, list<string>}
     */
    protected function parse(string $contents): array
    {
        $blank = fn (string $line): bool => Str::trim($line) === '';

        $lines = Str::of($contents)
            ->split('/\R/')
            ->takeUntil(fn (string $line): bool => $line === self::SCISSORS)
            ->reject(fn (string $line): bool => Str::startsWith($line, '#'))
            ->skipWhile($blank)
            ->reverse()
            ->skipWhile($blank)
            ->reverse()
            ->values();

        return [$lines->first(default: ''), $lines->skip(1)->values()->all()];
    }

    /**
     * After the subject only a blank separator line followed by trailers
     * (e.g. Co-Authored-By) may appear — never a descriptive body.
     *
     * @param  list<string>  $lines
     */
    protected function trailersOnly(string $attribute, array $lines, Closure $fail): void
    {
        if ($lines === []) {
            return;
        }

        if (Str::trim($lines[0]) !== '') {
            $fail('the subject must be followed by a blank line before any trailers.');

            return;
        }

        foreach (array_slice($lines, 1) as $line) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9-]*: \S/', $line) !== 1) {
                $fail('descriptive bodies are not allowed — subject, blank line, then trailers like Co-Authored-By only.');

                return;
            }
        }
    }
}

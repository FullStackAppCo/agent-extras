<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Console\Commands\Git;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PhpToken;

class ExtractNonEssentialCommentsCommand extends Command
{
    /**
     * Comments that direct tooling rather than explain code, which are never
     * up for review.
     */
    protected const string DIRECTIVE = '/@(phpstan|psalm|phan)-|@codeCoverageIgnore|@noRector|@noinspection|@infection-ignore|phpcs:|php-cs-fixer-ignore/i';

    /**
     * A PHPDoc tag such as `@param` or `{@inheritDoc}`. Docblocks carrying
     * one hold type information for tooling, so they are never up for review.
     */
    protected const string PHPDOC_TAG = '/(?:^|[\s*{])@[a-zA-Z]/';

    /**
     * The lines of code shown after each comment so its value can be judged.
     */
    protected const int CONTEXT_LINES = 3;

    protected $signature = 'agent-extras:nonessential-comments
        {paths?* : The PHP files to inspect (defaults to the staged PHP files)}';

    protected $description = 'List the comments and tagless docblocks the staged changes add, with the code around them.';

    public function handle(): int
    {
        $paths = $this->argument('paths') ?: $this->stagedPaths();

        if ($paths === null) {
            $this->error('Unable to list the staged files.');

            return self::FAILURE;
        }

        Collection::make($paths)
            ->flatMap(fn (string $path): Collection => $this->report($path))
            ->each(function (string $block, int $index): void {
                if ($index > 0) {
                    $this->newLine();
                }

                Str::of($block)->explode(PHP_EOL)->each(fn (string $line) => $this->line($line));
            });

        return self::SUCCESS;
    }

    /**
     * @return list<string>|null
     */
    protected function stagedPaths(): ?array
    {
        $result = Process::run(['git', 'diff', '--cached', '--name-only', '--diff-filter=ACM', '--', '*.php']);

        if ($result->failed()) {
            return null;
        }

        return Str::of($result->output())->explode("\n")->map(fn (string $line): string => Str::trim($line))->filter()->values()->all();
    }

    /**
     * Each block of comments the staged copy of the file adds over HEAD,
     * rendered with its line numbers and the code that follows it.
     *
     * @return Collection<int, string>
     */
    protected function report(string $path): Collection
    {
        $staged = $this->show(":{$path}");

        if ($staged === null) {
            return new Collection;
        }

        $existing = $this->comments($this->show("HEAD:{$path}") ?? '')
            ->map(fn (PhpToken $token): string => Str::trim($token->text))
            ->countBy()
            ->all();

        $lines = Str::of($staged)->explode("\n")->all();

        return $this->blocks($staged)
            ->filter(function (Collection $block) use (&$existing): bool {
                $added = false;

                foreach ($block as $token) {
                    $text = Str::trim($token->text);

                    if (($existing[$text] ?? 0) > 0) {
                        $existing[$text]--;
                    } else {
                        $added = true;
                    }
                }

                return $added;
            })
            ->map(fn (Collection $block): string => $this->render($path, $block, $lines))
            ->values();
    }

    protected function show(string $object): ?string
    {
        $result = Process::run(['git', 'show', $object]);

        return $result->successful() ? $result->output() : null;
    }

    /**
     * The comments in the source, along with any docblocks that carry no
     * PHPDoc tags, leaving out tool directives.
     *
     * @return Collection<int, PhpToken>
     */
    protected function comments(string $source): Collection
    {
        return Collection::make(PhpToken::tokenize($source))
            ->filter($this->isComment(...))
            ->values();
    }

    protected function isComment(PhpToken $token): bool
    {
        if ($token->is(T_DOC_COMMENT)) {
            return preg_match(self::PHPDOC_TAG, $token->text) !== 1;
        }

        return $token->is(T_COMMENT) && preg_match(self::DIRECTIVE, $token->text) !== 1;
    }

    /**
     * Group comments on consecutive lines with no code between them, such as
     * a run of `//` lines, into one block so each is reviewed as a whole.
     *
     * @return Collection<int, Collection<int, PhpToken>>
     */
    protected function blocks(string $source): Collection
    {
        $blocks = [];
        $last = null;

        foreach (PhpToken::tokenize($source) as $token) {
            if ($token->is(T_WHITESPACE)) {
                continue;
            }

            if (! $this->isComment($token)) {
                $last = null;

                continue;
            }

            if ($last instanceof PhpToken && $token->line === $this->endLine($last) + 1) {
                $blocks[array_key_last($blocks)][] = $token;
            } else {
                $blocks[] = [$token];
            }

            $last = $token;
        }

        return Collection::make($blocks)->map(fn (array $block): Collection => new Collection($block));
    }

    protected function endLine(PhpToken $token): int
    {
        return $token->line + Str::substrCount(Str::rtrim($token->text, "\n"), "\n");
    }

    /**
     * @param  Collection<int, PhpToken>  $block
     * @param  list<string>  $lines
     */
    protected function render(string $path, Collection $block, array $lines): string
    {
        $start = $block->first()->line;
        $end = $this->endLine($block->last());
        $last = min(count($lines), $end + self::CONTEXT_LINES);
        $range = $start === $end ? "{$start}" : "{$start}-{$end}";

        return Collection::range($start, $last)
            ->map(fn (int $number): string => Str::rtrim(Str::padLeft((string) $number, Str::length((string) $last)).' | '.$lines[$number - 1]))
            ->prepend("{$path}:{$range}")
            ->implode(PHP_EOL);
    }
}

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
    protected const string DIRECTIVE = '/@(phpstan|psalm|phan)-|@codeCoverageIgnore|@noRector|@noinspection|@infection-ignore|phpcs:|php-cs-fixer-ignore/i';

    protected const string PHPDOC_TAG = '/(?:^|[\s*{])@[a-zA-Z]/';

    protected const int CONTEXT_LINES = 3;

    protected $signature = 'agent-extras:nonessential-comments
        {paths* : The staged PHP files to inspect}';

    protected $description = 'List the comments and tagless docblocks the staged changes add, with the code around them.';

    public function handle(): int
    {
        Collection::make($this->argument('paths'))
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

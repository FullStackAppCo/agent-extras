<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Git;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RationalizeCommentsCommandTest extends TestCase
{
    protected const string HEAD = <<<'PHP'
        <?php

        // Kept from before.
        $a = 1;
        PHP;

    #[Test]
    public function it_lists_the_comments_added_over_head_with_the_code_after_them(): void
    {
        $this->fakeGit(staged: <<<'PHP'
            <?php

            // Kept from before.
            $a = 1;
            // Loop over the items
            // and add them up
            $b = 2;
            PHP);

        $this->artisan('agent-extras:rationalize-comments')
            ->expectsOutputToContain('src/Foo.php:5-6')
            ->expectsOutputToContain('5 | // Loop over the items')
            ->expectsOutputToContain('7 | $b = 2;')
            ->doesntExpectOutputToContain('Kept from before')
            ->assertSuccessful();
    }

    #[Test]
    public function it_ignores_docblocks_and_tool_directives(): void
    {
        $this->fakeGit(staged: <<<'PHP'
            <?php

            /**
             * A docblock.
             */
            function foo(): void {}
            // @phpstan-ignore-next-line
            $a = foo();
            /* phpcs:ignore */
            $b = 2;
            PHP);

        $this->artisan('agent-extras:rationalize-comments')
            ->doesntExpectOutputToContain('src/Foo.php')
            ->assertSuccessful();
    }

    #[Test]
    public function it_splits_comments_separated_by_code_into_blocks(): void
    {
        $this->fakeGit(staged: <<<'PHP'
            <?php

            // First
            $a = 1;
            // Second
            $b = 2;
            PHP);

        $this->artisan('agent-extras:rationalize-comments')
            ->expectsOutputToContain('src/Foo.php:3')
            ->expectsOutputToContain('src/Foo.php:5')
            ->assertSuccessful();
    }

    #[Test]
    public function it_treats_every_comment_in_a_new_file_as_added(): void
    {
        $this->fakeGit(staged: self::HEAD, head: null);

        $this->artisan('agent-extras:rationalize-comments')
            ->expectsOutputToContain('src/Foo.php:3')
            ->assertSuccessful();
    }

    #[Test]
    public function it_inspects_the_given_paths(): void
    {
        Process::fake([
            '*show*HEAD:src/Bar.php*' => Process::result(self::HEAD),
            '*show*:src/Bar.php*' => Process::result(self::HEAD."\n// New\n"),
        ]);

        $this->artisan('agent-extras:rationalize-comments', ['paths' => ['src/Bar.php']])
            ->expectsOutputToContain('src/Bar.php:5')
            ->assertSuccessful();

        Process::assertNotRan(fn ($process): bool => Str::contains(implode(' ', (array) $process->command), 'diff'));
    }

    #[Test]
    public function it_fails_when_the_staged_files_cannot_be_listed(): void
    {
        Process::fake([
            '*diff*' => Process::result('', exitCode: 128),
        ]);

        $this->artisan('agent-extras:rationalize-comments')
            ->expectsOutputToContain('Unable to list the staged files.')
            ->assertFailed();
    }

    protected function fakeGit(string $staged, ?string $head = self::HEAD): void
    {
        Process::fake([
            '*diff*' => Process::result("src/Foo.php\n"),
            '*show*HEAD:src/Foo.php*' => $head === null ? Process::result('', exitCode: 128) : Process::result($head),
            '*show*:src/Foo.php*' => Process::result($staged),
        ]);
    }
}

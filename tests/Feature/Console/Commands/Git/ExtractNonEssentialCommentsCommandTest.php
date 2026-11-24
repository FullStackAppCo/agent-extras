<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Git;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExtractNonEssentialCommentsCommandTest extends TestCase
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

        $this->artisan('agent-extras:nonessential-comments', ['paths' => ['src/Foo.php']])
            ->expectsOutputToContain('src/Foo.php:5-6')
            ->expectsOutputToContain('5 | // Loop over the items')
            ->expectsOutputToContain('7 | $b = 2;')
            ->doesntExpectOutputToContain('Kept from before')
            ->assertSuccessful();
    }

    #[Test]
    public function it_ignores_docblocks_with_phpdoc_tags_and_tool_directives(): void
    {
        $this->fakeGit(staged: <<<'PHP'
            <?php

            /**
             * A docblock.
             *
             * @return void
             */
            function foo(): void {}
            /** {@inheritDoc} */
            function bar(): void {}
            // @phpstan-ignore-next-line
            $a = foo();
            /* phpcs:ignore */
            $b = 2;
            PHP);

        $this->artisan('agent-extras:nonessential-comments', ['paths' => ['src/Foo.php']])
            ->doesntExpectOutputToContain('src/Foo.php')
            ->assertSuccessful();
    }

    #[Test]
    public function it_lists_docblocks_without_phpdoc_tags(): void
    {
        $this->fakeGit(staged: <<<'PHP'
            <?php

            /**
             * Get the user.
             */
            function getUser(): void {}
            PHP);

        $this->artisan('agent-extras:nonessential-comments', ['paths' => ['src/Foo.php']])
            ->expectsOutputToContain('src/Foo.php:3-5')
            ->expectsOutputToContain('4 |  * Get the user.')
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

        $this->artisan('agent-extras:nonessential-comments', ['paths' => ['src/Foo.php']])
            ->expectsOutputToContain('src/Foo.php:3')
            ->expectsOutputToContain('src/Foo.php:5')
            ->assertSuccessful();
    }

    #[Test]
    public function it_treats_every_comment_in_a_new_file_as_added(): void
    {
        $this->fakeGit(staged: self::HEAD, head: null);

        $this->artisan('agent-extras:nonessential-comments', ['paths' => ['src/Foo.php']])
            ->expectsOutputToContain('src/Foo.php:3')
            ->assertSuccessful();
    }

    #[Test]
    public function it_handles_windows_line_endings(): void
    {
        $this->fakeGit(staged: "<?php\r\n\r\n/*\r\n * One\r\n */\r\n// Two\r\n\$a = 1;\r\n");

        $this->artisan('agent-extras:nonessential-comments', ['paths' => ['src/Foo.php']])
            ->expectsOutputToContain('src/Foo.php:3-6')
            ->expectsOutputToContain('7 | $a = 1;')
            ->assertSuccessful();
    }

    protected function fakeGit(string $staged, ?string $head = self::HEAD): void
    {
        Process::fake([
            '*show*HEAD:src/Foo.php*' => $head === null ? Process::result('', exitCode: 128) : Process::result($head),
            '*show*:src/Foo.php*' => Process::result($staged),
        ]);
    }
}

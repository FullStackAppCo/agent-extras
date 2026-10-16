<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Git;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FormatCommitMessageCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    protected function messageFile(string $contents): string
    {
        Storage::put('commit-msg', $contents);

        return Storage::path('commit-msg');
    }

    #[Test]
    #[TestWith(["Added qa checks\n"])]
    #[TestWith(["Added qa checks #8\n"])]
    #[TestWith(["Added qa checks #8\n\nCo-Authored-By: Claude Fable 5 <noreply@anthropic.com>\n"])]
    #[TestWith(["Added qa checks #8\n\nCo-Authored-By: Jane Doe <jane@example.com>\n"])]
    #[TestWith(["Upgraded to PHP 8.4\n"])]
    public function it_accepts_messages_following_the_convention(string $message): void
    {
        $this->artisan('agent-extras:format-commit-message', ['file' => $this->messageFile($message)])
            ->expectsOutputToContain('Commit message is valid.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_strips_the_claude_trailers(): void
    {
        $file = $this->messageFile(
            "Added qa checks #8\n\n"
            ."Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>\n"
            ."Claude-Session: https://claude.ai/code/session_0123456789\n",
        );

        $this->artisan('agent-extras:format-commit-message', ['file' => $file])
            ->expectsOutputToContain('Stripped the Claude trailers.')
            ->expectsOutputToContain('Commit message is valid.')
            ->assertSuccessful();

        $this->assertSame("Added qa checks #8\n", Storage::get('commit-msg'));
    }

    #[Test]
    public function it_keeps_trailers_that_are_not_claudes(): void
    {
        $file = $this->messageFile(
            "Added qa checks #8\n\n"
            ."Co-Authored-By: Jane Doe <jane@example.com>\n"
            ."Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>\n"
            ."Claude-Session: https://claude.ai/code/session_0123456789\n",
        );

        $this->artisan('agent-extras:format-commit-message', ['file' => $file])
            ->expectsOutputToContain('Stripped the Claude trailers.')
            ->assertSuccessful();

        $this->assertSame(
            "Added qa checks #8\n\nCo-Authored-By: Jane Doe <jane@example.com>\n",
            Storage::get('commit-msg'),
        );
    }

    #[Test]
    public function it_leaves_a_conforming_message_file_untouched(): void
    {
        $message = "Added qa checks #8\n\nCo-Authored-By: Jane Doe <jane@example.com>\n";
        $file = $this->messageFile($message);

        $this->artisan('agent-extras:format-commit-message', ['file' => $file])->assertSuccessful();

        $this->assertSame($message, Storage::get('commit-msg'));
    }

    #[Test]
    #[TestWith(["added qa checks\n"])]
    #[TestWith(["Added qa checks.\n"])]
    public function it_rejects_subjects_that_are_not_terse_sentence_case(string $message): void
    {
        $this->artisan('agent-extras:format-commit-message', ['file' => $this->messageFile($message)])
            ->expectsOutputToContain('terse sentence in sentence case')
            ->expectsOutputToContain('See the commit skill')
            ->assertFailed();
    }

    #[Test]
    public function it_rejects_a_subject_longer_than_72_characters(): void
    {
        $message = 'Added '.Str::repeat('a very ', 12)."long subject line\n";

        $this->artisan('agent-extras:format-commit-message', ['file' => $this->messageFile($message)])
            ->expectsOutputToContain('exceeds 72 characters')
            ->assertFailed();
    }

    #[Test]
    #[TestWith(["Added #8 qa checks\n"])]
    #[TestWith(["Added qa checks closes #8\n"])]
    #[TestWith(["Added qa checks for issue 8\n"])]
    #[TestWith(["Added qa checks 8\n"])]
    public function it_rejects_a_misplaced_issue_number(string $message): void
    {
        $this->artisan('agent-extras:format-commit-message', ['file' => $this->messageFile($message)])
            ->expectsOutputToContain('misplaces the issue number')
            ->assertFailed();
    }

    #[Test]
    #[TestWith(["Added qa checks #8\n\nThis adds several qa checks to the pipeline.\n"])]
    #[TestWith(["Added qa checks #8\nCo-Authored-By: Jane Doe <jane@example.com>\n"])]
    public function it_rejects_descriptive_bodies_and_missing_blank_lines(string $message): void
    {
        $this->artisan('agent-extras:format-commit-message', ['file' => $this->messageFile($message)])
            ->expectsOutputToContain('Commit message check failed')
            ->assertFailed();
    }

    #[Test]
    public function it_rejects_an_empty_message(): void
    {
        $this->artisan('agent-extras:format-commit-message', ['file' => $this->messageFile("\n# Comment only\n")])
            ->expectsOutputToContain('the message is empty.')
            ->assertFailed();
    }

    #[Test]
    #[TestWith(["Merge branch 'qa-workflow-8' into 'main'\n\nSee merge request fullstackappco/website!8\n"])]
    #[TestWith(["Revert \"Added qa checks #8\"\n\nThis reverts commit 0123456789abcdef.\n"])]
    #[TestWith(["fixup! Added qa checks #8\n"])]
    public function it_exempts_git_generated_messages(string $message): void
    {
        $this->artisan('agent-extras:format-commit-message', ['file' => $this->messageFile($message)])
            ->expectsOutputToContain('exempt from the convention')
            ->assertSuccessful();
    }

    #[Test]
    public function it_ignores_comment_lines_and_the_verbose_diff(): void
    {
        $file = $this->messageFile(
            "Added qa checks #8\n\n"
            ."# Please enter the commit message for your changes.\n"
            ."# ------------------------ >8 ------------------------\n"
            ."diff --git a/foo b/foo\n",
        );

        $this->artisan('agent-extras:format-commit-message', ['file' => $file])
            ->expectsOutputToContain('Commit message is valid.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_fails_when_the_file_cannot_be_read(): void
    {
        $this->artisan('agent-extras:format-commit-message', ['file' => '/nonexistent/commit-msg'])
            ->expectsOutputToContain('Unable to read commit message file')
            ->assertFailed();
    }
}

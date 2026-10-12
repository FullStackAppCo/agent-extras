<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras;

use FullStackAppCo\AgentExtras\Console\Commands\Git\FormatCommitMessageCommand;
use FullStackAppCo\AgentExtras\Console\Commands\Git\ValidateBranchCommand;
use FullStackAppCo\AgentExtras\Console\Commands\Git\ValidateTagCommand;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FormatCommitMessageCommand::class,
                ValidateBranchCommand::class,
                ValidateTagCommand::class,
            ]);
        }
    }
}

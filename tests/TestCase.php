<?php

declare(strict_types=1);

namespace Tests;

use FullStackAppCo\AgentExtras\ServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Override;

abstract class TestCase extends BaseTestCase
{
    /**
     * @return list<class-string<\Illuminate\Support\ServiceProvider>>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [ServiceProvider::class];
    }
}

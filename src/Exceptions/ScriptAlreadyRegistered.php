<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Exceptions;

use Exception;

class ScriptAlreadyRegistered extends Exception
{
    public function __construct(string $name)
    {
        parent::__construct("Script '$name' already exists.");
    }
}

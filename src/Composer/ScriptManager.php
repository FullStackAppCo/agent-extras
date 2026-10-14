<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Composer;

use FullStackAppCo\AgentExtras\Exceptions\ScriptAlreadyRegistered;

class ScriptManager
{
    /**
     * @param  array  $composer  Array representation of composer.json
     */
    public function __construct(private array $composer)
    {
        //
    }

    /**
     * @throws ScriptAlreadyRegistered
     */
    public function add(string $name, array $scripts, bool $force = false): self
    {
        $existing = $this->composer['scripts'] ?? [];

        if (array_key_exists($name, $existing) && ! $force) {
            throw new ScriptAlreadyRegistered($name);
        }

        data_set($this->composer, "scripts.$name", $scripts);

        return $this;
    }

    /**
     * Array representation of composer.json including any added scripts.
     */
    public function toArray(): array
    {
        return $this->composer;
    }
}

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
     * Append the given entries to a script, creating it if necessary and
     * leaving out any entries the script already lists.
     *
     * @param  list<string>  $scripts
     * @return list<string> The entries which were appended
     */
    public function append(string $name, array $scripts): array
    {
        $existing = (array) ($this->composer['scripts'][$name] ?? []);
        $missing = array_values(array_diff($scripts, $existing));

        if ($missing !== []) {
            data_set($this->composer, "scripts.$name", [...$existing, ...$missing]);
        }

        return $missing;
    }

    /**
     * Array representation of composer.json including any added scripts.
     */
    public function toArray(): array
    {
        return $this->composer;
    }
}

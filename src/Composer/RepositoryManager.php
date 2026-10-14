<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Composer;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RepositoryManager
{
    /**
     * @param  array  $composer  Array representation of composer.json
     */
    public function __construct(private array $composer)
    {
        //
    }

    public function add(string $name, string $url): self
    {
        if ($this->has($url)) {
            return $this;
        }

        $this->composer['repositories'][] = [
            'name' => $name,
            'type' => 'vcs',
            'url' => $url,
        ];

        return $this;
    }

    /**
     * Whether a VCS repository with the given URL is already registered,
     * ignoring case, a trailing slash and a trailing .git suffix.
     */
    public function has(string $url): bool
    {
        return $this->normalizedUrls()->contains($this->normalizeUrl($url));
    }

    /**
     * Array representation of composer.json including any added repositories.
     */
    public function toArray(): array
    {
        return $this->composer;
    }

    /**
     * @return Collection<int, string>
     */
    private function normalizedUrls(): Collection
    {
        return Collection::make(data_get($this->composer, 'repositories'))
            ->filter($this->isVcsRepository(...))
            ->map(fn (array $entry): string => $this->normalizeUrl((string) data_get($entry, 'url')));
    }

    private function normalizeUrl(string $url): string
    {
        return Str::of($url)->rtrim('/')->chopEnd('.git')->lower()->value();
    }

    private function isVcsRepository(mixed $entry): bool
    {
        if (! is_array($entry)) {
            return false;
        }

        return data_get($entry, 'type') === 'vcs';
    }
}

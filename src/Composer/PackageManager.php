<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Composer;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Process;

use function Illuminate\Support\php_binary;

readonly class PackageManager
{
    private Closure $output;

    public function __construct(
        public string $composerToUse,
        public array $composerJson,
    ) {
        $this->output = $this->discard(...);
    }

    /**
     * Receive Composer's output, along with this manager's own status lines,
     * as ($type, $output) pairs in the same shape as a Process callback.
     */
    public function output(Closure $callback): static
    {
        return clone ($this, ['output' => $callback]);
    }

    public function require(string ...$packages): bool
    {
        $missing = $this->missing(...$packages);

        if ($missing === []) {
            $this->write('The dependencies are already required.');

            return true;
        }

        $this->write('Requiring '.Arr::join($missing, ', ', ' and ').'.');

        $command = [
            ...($this->composerToUse === 'global' ? ['composer'] : [php_binary(), $this->composerToUse]),
            'require',
            '--dev',
            ...$missing,
        ];

        $result = Process::forever()
            ->path(App::basePath())
            ->env(['COMPOSER_MEMORY_LIMIT' => '-1'])
            ->run($command, $this->output);

        return $result->successful();
    }

    private function write(string $line): void
    {
        ($this->output)('out', $line.PHP_EOL);
    }

    /**
     * The default output callback, which discards everything.
     */
    private function discard(string $type, string $output): void
    {
        //
    }

    /**
     * @return list<string>
     */
    private function missing(string ...$packages): array
    {
        $required = array_keys([
            ...(array) ($this->composerJson['require'] ?? []),
            ...(array) ($this->composerJson['require-dev'] ?? []),
        ]);

        return array_values(Arr::map(
            Arr::except($packages, $required),
            static fn (string $version, string $package): string => $version === '' ? $package : "{$package}:{$version}",
        ));
    }
}

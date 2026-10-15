<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Renders the PHPStan and Rector configuration stubs for a project, filling
 * in the directories they cover according to the project type.
 */
class ConfigurationStubs
{
    /**
     * The directories the configuration covers in a Laravel application.
     *
     * @var list<string>
     */
    protected const array APPLICATION_PATHS = ['app', 'bootstrap', 'config', 'database', 'routes', 'tests'];

    /**
     * The directories within those paths which hold generated files.
     *
     * @var list<string>
     */
    protected const array APPLICATION_SKIP = ['bootstrap/cache'];

    public function __construct(protected string $basePath) {}

    /**
     * The configuration files, each published from its `.stub` namesake.
     *
     * @var list<string>
     */
    public const array FILES = ['phpstan.neon', 'rector.php'];

    public static function stub(string $file): string
    {
        return dirname(__DIR__)."/stubs/{$file}.stub";
    }

    /**
     * Render the named configuration file (e.g. `rector.php`) for the project.
     */
    public function render(string $file): string
    {
        $contents = File::get(self::stub($file));

        foreach ($this->placeholders()[$file] ?? [] as $name => $lines) {
            $contents = Str::replace(
                "{{ {$name} }}\n",
                $lines === [] ? '' : implode("\n", $lines)."\n",
                $contents,
            );
        }

        return $contents;
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    protected function placeholders(): array
    {
        [$paths, $skip] = $this->paths();

        return [
            'phpstan.neon' => [
                'paths' => Arr::map($paths, fn (string $path): string => "        - {$path}/"),
            ],
            'rector.php' => [
                'paths' => Arr::map($paths, fn (string $path): string => "        __DIR__.'/{$path}',"),
                'skip' => Arr::map($skip, fn (string $path): string => "        __DIR__.'/{$path}',"),
            ],
        ];
    }

    /**
     * The directories the configuration covers, and those it skips within
     * them. Laravel Zero keeps its code in `app` and a package in `src`.
     *
     * @return array{list<string>, list<string>}
     */
    protected function paths(): array
    {
        if (File::isFile("{$this->basePath}/artisan")) {
            return [self::APPLICATION_PATHS, self::APPLICATION_SKIP];
        }

        $source = File::isDirectory("{$this->basePath}/app") ? 'app' : 'src';

        return [[$source, 'tests'], []];
    }
}

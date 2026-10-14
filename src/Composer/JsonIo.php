<?php

declare(strict_types=1);

namespace FullStackAppCo\AgentExtras\Composer;

use Illuminate\Support\Facades\File;

class JsonIo
{
    public function __construct(public readonly string $path)
    {
        //
    }

    public function read(): array
    {
        return json_decode(File::get($this->path), true, 512, JSON_THROW_ON_ERROR);
    }

    public function write(array $data): void
    {
        File::put($this->path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }
}

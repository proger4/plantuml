<?php

declare(strict_types=1);

namespace Tools\Reconcile;

use Tools\Config;
use Tools\Jsonl;

final class RegistryMerger
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /** @return array{entities:list<array<string,mixed>>,relations:list<array<string,mixed>>,inheritance:list<array<string,mixed>>,discriminators:list<array<string,mixed>>,issues:list<array<string,mixed>>} */
    public function loadAll(): array
    {
        $base = $this->config->registryDir();
        return [
            'entities' => Jsonl::read($base . '/entities.jsonl'),
            'relations' => Jsonl::read($base . '/relations.jsonl'),
            'inheritance' => Jsonl::read($base . '/inheritance.jsonl'),
            'discriminators' => Jsonl::read($base . '/discriminators.jsonl'),
            'issues' => Jsonl::read($base . '/issues.jsonl'),
        ];
    }

    /** @param list<array<string,mixed>> $rows */
    public function write(string $name, array $rows): void
    {
        Jsonl::write($this->config->registryDir() . '/' . $name . '.jsonl', $rows);
    }
}

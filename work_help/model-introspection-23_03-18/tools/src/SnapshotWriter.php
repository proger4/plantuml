<?php

declare(strict_types=1);

namespace Tools;

final class SnapshotWriter
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /** @param array<string,mixed> $payload */
    public function write(string $caseType, string $subjectKey, array $payload): string
    {
        $dir = $this->config->snapshotsDir() . '/' . $caseType;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $name = SubjectKey::safeName($subjectKey) . '.json';
        $path = $dir . '/' . $name;
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        return $path;
    }
}

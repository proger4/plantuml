<?php

declare(strict_types=1);

namespace Tools\Uml;

use Tools\Config;
use Tools\SubjectKey;

final class UmlCollector
{
    private Config $config;
    private UmlTrimService $trimService;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->trimService = new UmlTrimService();
    }

    /** @return array{graph:array<string,mixed>,graph_path:string,warnings:list<string>,counts:array<string,int>} */
    public function collect(string $root, string $umlPath, array $limits): array
    {
        $res = $this->trimService->trim($umlPath, $root, $limits);
        $graph = $res['graph'];
        $warnings = $res['warnings'];

        $dir = $this->config->trimmedUmlDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir . '/' . SubjectKey::safeName($root) . '.graph.json';
        file_put_contents($path, json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");

        return [
            'graph' => $graph,
            'graph_path' => $path,
            'warnings' => $warnings,
            'counts' => [
                'classes' => count($graph['classes'] ?? []),
                'relation_edges' => count($graph['relation_edges'] ?? []),
                'inheritance_edges' => count($graph['inheritance_edges'] ?? []),
            ],
        ];
    }
}

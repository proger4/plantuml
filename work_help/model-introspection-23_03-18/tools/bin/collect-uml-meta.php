#!/usr/bin/env php8
<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Tools\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = __DIR__ . '/../src/' . $rel . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use Tools\Config;
use Tools\Jsonl;
use Tools\Uml\UmlCollector;

function parseArgs(array $argv): array
{
    $args = [];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $key = substr($arg, 2);
        $next = $argv[$i + 1] ?? null;
        if (is_string($next) && !str_starts_with($next, '--')) {
            $args[$key] = $next;
            $i++;
        } else {
            $args[$key] = true;
        }
    }
    return $args;
}

function rootMatch(string $subject, string $root): bool
{
    return $subject === $root || str_starts_with($subject, $root . '::');
}

$args = parseArgs($argv);
$rootClass = isset($args['root']) && is_string($args['root']) ? $args['root'] : null;
if ($rootClass === null || $rootClass === '') {
    fwrite(STDERR, "ERROR: --root is required\n");
    exit(2);
}

$format = isset($args['format']) && is_string($args['format']) ? $args['format'] : 'table';
$projectRoot = dirname(__DIR__, 2);
$config = Config::fromProjectRoot($projectRoot);
$config->ensureDirs();

$umlArg = isset($args['uml']) && is_string($args['uml']) ? $args['uml'] : null;
$umlPath = $umlArg !== null ? $config->resolvePath($umlArg) : $config->resolvePath((string)$config->get('uml_path', './docs/domain.puml'));

$collector = new UmlCollector($config);
$res = $collector->collect($rootClass, $umlPath, [
    'max_classes' => 40,
    'max_relation_edges' => 60,
    'max_hops' => 1,
]);

$registryDir = $config->registryDir();
$files = [
    $registryDir . '/relations.jsonl',
    $registryDir . '/inheritance.jsonl',
    $registryDir . '/discriminators.jsonl',
];

foreach ($files as $path) {
    $rows = Jsonl::read($path);
    foreach ($rows as &$row) {
        $subject = (string)($row['subject_key'] ?? '');
        if (!rootMatch($subject, $rootClass)) {
            continue;
        }
        $row['uml'] = [
            'graph_path' => $res['graph_path'],
            'counts' => $res['counts'],
        ];
    }
    unset($row);
    Jsonl::write($path, $rows);
}

$payload = [
    'ok' => true,
    'root' => $rootClass,
    'trimmed_graph_json' => $res['graph_path'],
    'counts' => $res['counts'],
    'warnings' => $res['warnings'],
];

if ($format === 'json') {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "collect-uml-meta: OK\n";
    echo "root: {$rootClass}\n";
    echo "graph: {$res['graph_path']}\n";
}

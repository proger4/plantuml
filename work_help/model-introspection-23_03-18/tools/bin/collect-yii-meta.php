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
use Tools\Yii\YiiBootstrap;
use Tools\Yii\YiiMetaCollector;

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

/** @param list<array<string,mixed>> $newRows */
function upsertRows(string $path, array $newRows): int
{
    $existing = Jsonl::read($path);
    $map = [];
    foreach ($existing as $row) {
        $k = ($row['record_type'] ?? '') . '|' . ($row['subject_key'] ?? '');
        $map[$k] = $row;
    }
    foreach ($newRows as $row) {
        $k = ($row['record_type'] ?? '') . '|' . ($row['subject_key'] ?? '');
        $map[$k] = $row;
    }
    $rows = array_values($map);
    Jsonl::write($path, $rows);
    return count($newRows);
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

$outputDirArg = isset($args['output-dir']) && is_string($args['output-dir']) ? $args['output-dir'] : null;
$registryDir = $outputDirArg !== null ? $config->resolvePath($outputDirArg) : $config->registryDir();
if (!is_dir($registryDir)) {
    mkdir($registryDir, 0777, true);
}

$bootstrap = new YiiBootstrap();
$boot = $bootstrap->bootstrap($config);

$collector = new YiiMetaCollector($config);
$result = $collector->collect($rootClass);
$warnings = array_merge($boot['warnings'], $result['warnings']);

$entitiesPath = $registryDir . '/entities.jsonl';
$relationsPath = $registryDir . '/relations.jsonl';
$inheritancePath = $registryDir . '/inheritance.jsonl';

$countEntities = upsertRows($entitiesPath, $result['entities']);
$countRelations = upsertRows($relationsPath, $result['relations']);
$countInheritance = upsertRows($inheritancePath, $result['inheritance']);

$payload = [
    'ok' => true,
    'root' => $rootClass,
    'written_files' => [$entitiesPath, $relationsPath, $inheritancePath],
    'counts' => [
        'entities' => $countEntities,
        'relations' => $countRelations,
        'inheritance' => $countInheritance,
    ],
    'warnings' => $warnings,
];

if ($format === 'json') {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "collect-yii-meta: OK\n";
    echo "root: {$rootClass}\n";
    echo "entities: {$countEntities}\n";
    echo "relations: {$countRelations}\n";
    echo "inheritance: {$countInheritance}\n";
}

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
use Tools\Sql\SqlSchemaCollector;

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

function shortName(string $fqcn): string
{
    $parts = explode('\\', $fqcn);
    return (string)end($parts);
}

function guessTable(string $fqcn): string
{
    $short = shortName($fqcn);
    $snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
    return $snake . 's';
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

$sqlArg = isset($args['sql']) && is_string($args['sql']) ? $args['sql'] : null;
$sqlPath = $sqlArg !== null ? $config->resolvePath($sqlArg) : $config->resolvePath((string)$config->get('sql_dump_path', './var/schema.sql'));

$collector = new SqlSchemaCollector();
$sqlMeta = $collector->collect($sqlPath);

$registryDir = $config->registryDir();
$entitiesPath = $registryDir . '/entities.jsonl';
$relationsPath = $registryDir . '/relations.jsonl';
$discPath = $registryDir . '/discriminators.jsonl';

$entities = Jsonl::read($entitiesPath);
$relations = Jsonl::read($relationsPath);
$discriminators = Jsonl::read($discPath);

$tableForRoot = guessTable($rootClass);
$tableMeta = $sqlMeta['tables'][$tableForRoot] ?? null;

foreach ($entities as &$entity) {
    $subject = (string)($entity['subject_key'] ?? '');
    if (!rootMatch($subject, $rootClass)) {
        continue;
    }
    $tableName = (string)($entity['yii']['table_name'] ?? $tableForRoot);
    $entity['sql'] = [
        'table' => $tableName,
        'table_meta' => $sqlMeta['tables'][$tableName] ?? null,
    ];
}
unset($entity);

foreach ($relations as &$rel) {
    $subject = (string)($rel['subject_key'] ?? '');
    if (!rootMatch($subject, $rootClass)) {
        continue;
    }
    $owner = (string)($rel['yii']['owner'] ?? $rootClass);
    $ownerTable = guessTable($owner);
    $fks = array_values(array_filter(
        $sqlMeta['fks'],
        static fn (array $fk): bool => (string)($fk['table'] ?? '') === $ownerTable
    ));
    $rel['sql'] = [
        'owner_table' => $ownerTable,
        'fks' => $fks,
        'junction_candidates' => $sqlMeta['junction_candidates'],
    ];
}
unset($rel);

$discSubject = $rootClass . '::discriminator';
$discColumns = array_values(array_filter(
    $sqlMeta['discriminator_candidates'],
    static fn (array $row): bool => (string)($row['table'] ?? '') === $tableForRoot
));

$discriminatorRecord = [
    'record_type' => 'DISCRIMINATOR_RECORD',
    'subject_key' => $discSubject,
    'status' => 'GAP',
    'issue_code' => null,
    'confidence' => 0.4,
    'candidate_columns' => $discColumns,
    'candidate_values' => [],
    'usage' => new stdClass(),
    'uml' => new stdClass(),
    'sql' => [
        'table' => $tableForRoot,
        'table_meta' => $tableMeta,
    ],
];

$updated = false;
foreach ($discriminators as $idx => $existing) {
    if (($existing['subject_key'] ?? null) === $discSubject) {
        $discriminators[$idx] = array_replace($existing, $discriminatorRecord);
        $updated = true;
        break;
    }
}
if (!$updated) {
    $discriminators[] = $discriminatorRecord;
}

Jsonl::write($entitiesPath, $entities);
Jsonl::write($relationsPath, $relations);
Jsonl::write($discPath, $discriminators);

$payload = [
    'ok' => true,
    'root' => $rootClass,
    'sql_file' => $sqlPath,
    'enriched_files' => [$entitiesPath, $relationsPath, $discPath],
    'counts' => [
        'tables' => count($sqlMeta['tables']),
        'fks' => count($sqlMeta['fks']),
        'junction_candidates' => count($sqlMeta['junction_candidates']),
        'discriminator_candidates' => count($sqlMeta['discriminator_candidates']),
    ],
    'warnings' => $sqlMeta['warnings'],
];

if ($format === 'json') {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "collect-sql-meta: OK\n";
    echo "root: {$rootClass}\n";
    echo "tables: " . count($sqlMeta['tables']) . "\n";
    echo "fks: " . count($sqlMeta['fks']) . "\n";
}

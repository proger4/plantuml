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
use Tools\SubjectKey;
use Tools\Usage\UsageCollector;

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
$subject = isset($args['subject']) && is_string($args['subject']) ? $args['subject'] : null;
$rootClass = isset($args['root']) && is_string($args['root']) ? $args['root'] : null;
if (($rootClass === null || $rootClass === '') && $subject !== null) {
    $rootClass = SubjectKey::rootClass($subject);
}
if ($rootClass === null || $rootClass === '') {
    fwrite(STDERR, "ERROR: --root or --subject is required\n");
    exit(2);
}

$format = isset($args['format']) && is_string($args['format']) ? $args['format'] : 'table';
$deep = isset($args['deep']);

$projectRoot = dirname(__DIR__, 2);
$config = Config::fromProjectRoot($projectRoot);
$config->ensureDirs();

$collector = new UsageCollector($config);
$usage = $collector->collect($rootClass, $subject, $deep);

$snippetFile = $config->snapshotsDir() . '/_usage/' . SubjectKey::safeName($subject ?? $rootClass) . '.json';
file_put_contents($snippetFile, json_encode($usage['snippets'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

$registryDir = $config->registryDir();
$files = [
    $registryDir . '/relations.jsonl',
    $registryDir . '/inheritance.jsonl',
    $registryDir . '/discriminators.jsonl',
];

foreach ($files as $path) {
    $rows = Jsonl::read($path);
    foreach ($rows as &$row) {
        $subjectKey = (string)($row['subject_key'] ?? '');
        if ($subject !== null) {
            if ($subjectKey !== $subject) {
                continue;
            }
        } else {
            if (!rootMatch($subjectKey, $rootClass)) {
                continue;
            }
        }

        $row['usage'] = [
            'patterns' => $usage['patterns'],
            'snippet_file' => $snippetFile,
            'scanned_files' => $usage['scanned_files'],
        ];
    }
    unset($row);
    Jsonl::write($path, $rows);
}

$payload = [
    'ok' => true,
    'root' => $rootClass,
    'subject' => $subject,
    'patterns' => $usage['patterns'],
    'snippet_files' => [$snippetFile],
    'warnings' => $usage['warnings'],
];

if ($format === 'json') {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "collect-usage: OK\n";
    echo "root: {$rootClass}\n";
    if ($subject !== null) {
        echo "subject: {$subject}\n";
    }
    echo "snippets: {$snippetFile}\n";
}

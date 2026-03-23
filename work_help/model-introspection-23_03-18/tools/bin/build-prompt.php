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
use Tools\Llm\PromptBuilder;
use Tools\SnapshotWriter;
use Tools\SubjectKey;

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
if (($subject === null || $subject === '') && ($rootClass === null || $rootClass === '')) {
    fwrite(STDERR, "ERROR: --subject or --root is required\n");
    exit(2);
}
$format = isset($args['format']) && is_string($args['format']) ? $args['format'] : 'table';

$projectRoot = dirname(__DIR__, 2);
$config = Config::fromProjectRoot($projectRoot);
$config->ensureDirs();

$promptBuilder = new PromptBuilder($config);
$snapshotWriter = new SnapshotWriter($config);

$subjects = [];
if ($subject !== null && $subject !== '') {
    $subjects[] = $subject;
} else {
    $issues = Jsonl::read($config->registryDir() . '/issues.jsonl');
    foreach ($issues as $issue) {
        $subjectKey = (string)($issue['subject_key'] ?? '');
        $status = (string)($issue['status'] ?? '');
        if (!rootMatch($subjectKey, (string)$rootClass)) {
            continue;
        }
        if (!in_array($status, ['GAP', 'CONFLICT', 'MANUAL'], true)) {
            continue;
        }
        $subjects[] = $subjectKey;
    }
    if ($subjects === [] && $rootClass !== null) {
        $subjects[] = (string)$rootClass;
    }
}

$subjects = array_values(array_unique($subjects));
$snapshots = [];
foreach ($subjects as $subjectKey) {
    $snapshot = $promptBuilder->buildForSubject($subjectKey);
    $caseType = SubjectKey::caseType($subjectKey);
    $path = $snapshotWriter->write($caseType, $subjectKey, $snapshot);
    $snapshots[] = $path;
}

$payload = [
    'ok' => true,
    'subject' => $subject,
    'root' => $rootClass,
    'created_count' => count($snapshots),
    'snapshot_file' => $snapshots[0] ?? null,
    'snapshots' => $snapshots,
];

if ($format === 'json') {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "build-prompt: OK\n";
    echo "created: " . count($snapshots) . "\n";
    if (isset($snapshots[0])) {
        echo "snapshot: {$snapshots[0]}\n";
    }
}

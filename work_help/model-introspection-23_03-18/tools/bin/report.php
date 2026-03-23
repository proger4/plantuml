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
$subject = isset($args['subject']) && is_string($args['subject']) ? $args['subject'] : null;
if (($rootClass === null || $rootClass === '') && ($subject === null || $subject === '')) {
    fwrite(STDERR, "ERROR: --root or --subject is required\n");
    exit(2);
}
$format = isset($args['format']) && is_string($args['format']) ? $args['format'] : 'md';

$projectRoot = dirname(__DIR__, 2);
$config = Config::fromProjectRoot($projectRoot);
$config->ensureDirs();

$issues = Jsonl::read($config->registryDir() . '/issues.jsonl');
$filtered = [];
foreach ($issues as $row) {
    $subjectKey = (string)($row['subject_key'] ?? '');
    if ($subject !== null && $subject !== '') {
        if ($subjectKey !== $subject) {
            continue;
        }
    } elseif ($rootClass !== null && $rootClass !== '') {
        if (!rootMatch($subjectKey, $rootClass)) {
            continue;
        }
    }
    $filtered[] = $row;
}

$statusCounters = [
    'OK' => 0,
    'GAP' => 0,
    'CONFLICT' => 0,
    'MANUAL' => 0,
    'DROP' => 0,
    'UNSUPPORTED' => 0,
];
$issueCounts = [];
$unresolved = [];

foreach ($filtered as $row) {
    $status = (string)($row['status'] ?? 'GAP');
    if (isset($statusCounters[$status])) {
        $statusCounters[$status]++;
    }

    $issueCode = (string)($row['issue_code'] ?? 'GENERIC_UNKNOWN_PATTERN');
    $issueCounts[$issueCode] = ($issueCounts[$issueCode] ?? 0) + 1;

    if (in_array($status, ['GAP', 'CONFLICT', 'MANUAL', 'UNSUPPORTED'], true)) {
        $unresolved[] = [
            'subject_key' => $row['subject_key'] ?? '',
            'case_type' => $row['case_type'] ?? '',
            'status' => $status,
            'issue_code' => $issueCode,
        ];
    }
}

$issueSummary = [];
ksort($issueCounts);
foreach ($issueCounts as $code => $count) {
    $issueSummary[] = ['issue_code' => $code, 'count' => $count];
}

$target = $subject ?? (string)$rootClass;
$safeTarget = SubjectKey::safeName($target);
$stamp = gmdate('Ymd_His');
$reportPath = $config->reportsDir() . '/report-' . $safeTarget . '-' . $stamp . ($format === 'json' ? '.json' : '.md');

$payload = [
    'ok' => true,
    'target' => $target,
    'format' => $format,
    'status_counters' => $statusCounters,
    'unresolved' => $unresolved,
    'issue_summary' => $issueSummary,
    'report_path' => $reportPath,
];

if ($format === 'json') {
    file_put_contents($reportPath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

$md = "# Report\n\n";
$md .= "Target: `{$target}`\n\n";
$md .= "## Counters\n\n";
foreach ($statusCounters as $status => $count) {
    $md .= "- {$status}: {$count}\n";
}
$md .= "\n## Unresolved\n\n";
foreach ($unresolved as $row) {
    $md .= '- ' . ($row['subject_key'] ?? '') . ' | ' . ($row['case_type'] ?? '') . ' | ' . ($row['status'] ?? '') . ' | ' . ($row['issue_code'] ?? '') . "\n";
}

file_put_contents($reportPath, $md);

echo "report: OK\n";
echo "target: {$target}\n";
echo "report_path: {$reportPath}\n";

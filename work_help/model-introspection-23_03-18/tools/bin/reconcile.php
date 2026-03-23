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
use Tools\Reconcile\IssueFactory;
use Tools\Reconcile\RegistryMerger;
use Tools\Reconcile\StatusDecider;

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

/**
 * @param list<array<string,mixed>> $issues
 * @param array<string,mixed> $issue
 * @return list<array<string,mixed>>
 */
function upsertIssue(array $issues, array $issue): array
{
    $subject = (string)($issue['subject_key'] ?? '');
    $case = (string)($issue['case_type'] ?? '');
    foreach ($issues as $idx => $existing) {
        if (($existing['subject_key'] ?? null) === $subject && ($existing['case_type'] ?? null) === $case) {
            $issues[$idx] = $issue;
            return $issues;
        }
    }
    $issues[] = $issue;
    return $issues;
}

$args = parseArgs($argv);
$rootClass = isset($args['root']) && is_string($args['root']) ? $args['root'] : null;
$subject = isset($args['subject']) && is_string($args['subject']) ? $args['subject'] : null;
if (($rootClass === null || $rootClass === '') && ($subject === null || $subject === '')) {
    fwrite(STDERR, "ERROR: --root or --subject is required\n");
    exit(2);
}
$format = isset($args['format']) && is_string($args['format']) ? $args['format'] : 'table';

$projectRoot = dirname(__DIR__, 2);
$config = Config::fromProjectRoot($projectRoot);
$config->ensureDirs();

$merger = new RegistryMerger($config);
$data = $merger->loadAll();

$decider = new StatusDecider();
$issueFactory = new IssueFactory();

$statusCounters = [
    'OK' => 0,
    'GAP' => 0,
    'CONFLICT' => 0,
    'MANUAL' => 0,
    'DROP' => 0,
    'UNSUPPORTED' => 0,
];
$processed = 0;
$issues = $data['issues'];

$apply = static function (array &$rows, string $type) use (&$issues, &$statusCounters, &$processed, $decider, $issueFactory, $rootClass, $subject): void {
    foreach ($rows as &$row) {
        $subjectKey = (string)($row['subject_key'] ?? '');
        if ($subject !== null) {
            if ($subjectKey !== $subject) {
                continue;
            }
        } elseif ($rootClass !== null) {
            if (!rootMatch($subjectKey, $rootClass)) {
                continue;
            }
        }

        $decision = $decider->decide($row);
        $row['status'] = $decision['status'];
        $row['issue_code'] = $decision['issue_code'];
        $row['confidence'] = $decision['confidence'];

        $status = $decision['status'];
        if (isset($statusCounters[$status])) {
            $statusCounters[$status]++;
        }

        $issue = $issueFactory->fromDecision($row, $decision);
        $issues = upsertIssue($issues, $issue);
        $processed++;
    }
    unset($row);
};

$apply($data['relations'], 'RELATION_RECORD');
$apply($data['inheritance'], 'INHERITANCE_RECORD');
$apply($data['discriminators'], 'DISCRIMINATOR_RECORD');

$merger->write('relations', $data['relations']);
$merger->write('inheritance', $data['inheritance']);
$merger->write('discriminators', $data['discriminators']);
$merger->write('issues', $issues);

$payload = [
    'ok' => true,
    'processed_subjects' => $processed,
    'status_counters' => $statusCounters,
    'issues_upserted' => count($issues),
];

if ($format === 'json') {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "reconcile: OK\n";
    echo "processed: {$processed}\n";
    echo "issues: " . count($issues) . "\n";
}

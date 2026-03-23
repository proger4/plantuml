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
use Tools\Llm\LlmClient;
use Tools\Llm\LlmResponseValidator;
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

$args = parseArgs($argv);
$snapshotArg = isset($args['snapshot']) && is_string($args['snapshot']) ? $args['snapshot'] : null;
if ($snapshotArg === null || $snapshotArg === '') {
    fwrite(STDERR, "ERROR: --snapshot is required\n");
    exit(2);
}
$format = isset($args['format']) && is_string($args['format']) ? $args['format'] : 'table';

$projectRoot = dirname(__DIR__, 2);
$config = Config::fromProjectRoot($projectRoot);
$config->ensureDirs();

$snapshotPath = $config->resolvePath($snapshotArg);
if (!is_file($snapshotPath)) {
    fwrite(STDERR, "ERROR: snapshot not found\n");
    exit(2);
}

$snapshot = json_decode((string)file_get_contents($snapshotPath), true);
if (!is_array($snapshot)) {
    fwrite(STDERR, "ERROR: snapshot is not valid JSON object\n");
    exit(3);
}

$subjectKey = (string)($snapshot['subject_key'] ?? 'unknown');
$safe = SubjectKey::safeName($subjectKey) . '-' . gmdate('Ymd_His');
$rawPath = $config->llmResultsDir() . '/' . $safe . '.raw.txt';
$parsedPath = $config->llmResultsDir() . '/' . $safe . '.json';

$client = new LlmClient($config);
$validator = new LlmResponseValidator();

$attempts = 0;
$validated = null;
$raw = '';
$lastError = null;
while ($attempts < 2) {
    $attempts++;
    $call = $client->call($snapshot);
    $raw = (string)($call['raw'] ?? '');
    file_put_contents($rawPath, $raw . "\n");

    $validated = $validator->validateRaw($raw);
    if (($validated['ok'] ?? false) === true) {
        break;
    }
    $lastError = (string)($validated['error'] ?? 'invalid llm response');
}

if (!is_array($validated) || ($validated['ok'] ?? false) !== true || !is_array($validated['data'] ?? null)) {
    $payload = [
        'ok' => false,
        'snapshot' => $snapshotPath,
        'raw_response_file' => $rawPath,
        'parsed_response_file' => null,
        'validated' => false,
        'retries_used' => max(0, $attempts - 1),
        'error' => $lastError,
    ];
    if ($format === 'json') {
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        echo "call-llm: ERROR\n";
        echo "snapshot: {$snapshotPath}\n";
        echo "validation: ERROR\n";
    }
    exit(5);
}

file_put_contents($parsedPath, json_encode($validated['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

$payload = [
    'ok' => true,
    'subject_key' => $subjectKey,
    'snapshot' => $snapshotPath,
    'raw_response_file' => $rawPath,
    'parsed_response_file' => $parsedPath,
    'validated' => true,
    'retries_used' => max(0, $attempts - 1),
    'model' => $call['model'] ?? null,
    'role' => $call['role'] ?? null,
    'base_url' => $call['base_url'] ?? null,
];

if ($format === 'json') {
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "call-llm: OK\n";
    echo "subject: {$subjectKey}\n";
    echo "snapshot: {$snapshotPath}\n";
    echo "result: {$parsedPath}\n";
    echo "validation: OK\n";
    if (isset($payload['model']) && is_string($payload['model'])) {
        echo "model: {$payload['model']}\n";
    }
}

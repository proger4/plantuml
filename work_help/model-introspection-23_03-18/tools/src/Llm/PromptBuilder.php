<?php

declare(strict_types=1);

namespace Tools\Llm;

use Tools\Config;
use Tools\Jsonl;
use Tools\SubjectKey;

final class PromptBuilder
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /** @return array<string,mixed> */
    public function buildForSubject(string $subjectKey): array
    {
        $registry = $this->config->registryDir();
        $issues = Jsonl::read($registry . '/issues.jsonl');
        $relations = Jsonl::read($registry . '/relations.jsonl');
        $inheritance = Jsonl::read($registry . '/inheritance.jsonl');
        $discriminators = Jsonl::read($registry . '/discriminators.jsonl');

        $issue = $this->findBySubject($issues, $subjectKey);
        $record = $this->findBySubject($relations, $subjectKey)
            ?? $this->findBySubject($inheritance, $subjectKey)
            ?? $this->findBySubject($discriminators, $subjectKey)
            ?? [];

        $caseType = is_array($issue) && isset($issue['case_type'])
            ? (string)$issue['case_type']
            : SubjectKey::caseType($subjectKey);

        $status = is_array($issue) && isset($issue['status'])
            ? (string)$issue['status']
            : (string)($record['status'] ?? 'GAP');

        return [
            'snapshot_version' => 'v1',
            'subject_key' => $subjectKey,
            'case_type' => $caseType,
            'status' => $status,
            'issue_code' => is_array($issue) ? ($issue['issue_code'] ?? null) : null,
            'record_type' => $record['record_type'] ?? null,
            'evidence' => [
                'yii' => $record['yii'] ?? new \stdClass(),
                'sql' => $record['sql'] ?? new \stdClass(),
                'usage' => $record['usage'] ?? new \stdClass(),
                'uml' => $record['uml'] ?? new \stdClass(),
            ],
            'decision_contract' => [
                'allowed_actions' => ['DECIDE', 'NEED_MORE_USAGE', 'DROP_CANDIDATE'],
                'allowed_statuses' => ['OK', 'GAP', 'CONFLICT', 'MANUAL', 'DROP', 'UNSUPPORTED'],
                'required_keys' => [
                    'action',
                    'case_type',
                    'subject_key',
                    'status',
                    'issue_code',
                    'confidence',
                    'decision_basis',
                    'requested_evidence',
                    'candidate',
                ],
            ],
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>|null
     */
    private function findBySubject(array $rows, string $subjectKey): ?array
    {
        foreach ($rows as $row) {
            if (($row['subject_key'] ?? null) === $subjectKey) {
                return $row;
            }
        }
        return null;
    }
}

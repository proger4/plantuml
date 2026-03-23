<?php

declare(strict_types=1);

namespace Tools\Reconcile;

final class IssueFactory
{
    /**
     * @param array<string,mixed> $record
     * @param array{status:string,issue_code:?string,confidence:float,case_type:string} $decision
     * @return array<string,mixed>
     */
    public function fromDecision(array $record, array $decision): array
    {
        $subject = (string)($record['subject_key'] ?? '');
        $status = (string)($decision['status'] ?? 'GAP');
        $issue = $decision['issue_code'] ?? 'GENERIC_UNKNOWN_PATTERN';

        return [
            'record_type' => 'ISSUE_RECORD',
            'subject_key' => $subject,
            'case_type' => (string)$decision['case_type'],
            'status' => $status,
            'issue_code' => $issue,
            'summary' => $this->summary($subject, $status, (string)$issue),
        ];
    }

    private function summary(string $subject, string $status, string $issueCode): string
    {
        return sprintf('%s => %s (%s)', $subject, $status, $issueCode);
    }
}

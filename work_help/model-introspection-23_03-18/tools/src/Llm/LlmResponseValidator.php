<?php

declare(strict_types=1);

namespace Tools\Llm;

final class LlmResponseValidator
{
    /** @var list<string> */
    private array $required = [
        'action',
        'case_type',
        'subject_key',
        'status',
        'issue_code',
        'confidence',
        'decision_basis',
        'requested_evidence',
        'candidate',
    ];

    /** @return array{ok:bool,data:?array<string,mixed>,missing:list<string>,error:?string} */
    public function validateRaw(string $raw): array
    {
        $decoded = $this->decodeJsonObject($raw);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'data' => null,
                'missing' => [],
                'error' => 'cannot parse JSON object from response',
            ];
        }

        $missing = [];
        foreach ($this->required as $key) {
            if (!array_key_exists($key, $decoded)) {
                $missing[] = $key;
            }
        }

        return [
            'ok' => $missing === [],
            'data' => $decoded,
            'missing' => $missing,
            'error' => $missing === [] ? null : 'missing required keys',
        ];
    }

    /** @return array<string,mixed>|null */
    private function decodeJsonObject(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $first = strpos($raw, '{');
        $last = strrpos($raw, '}');
        if ($first === false || $last === false || $last <= $first) {
            return null;
        }

        $json = substr($raw, $first, $last - $first + 1);
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }
}

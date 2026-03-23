<?php

declare(strict_types=1);

namespace Tools\Llm;

use Tools\Config;

final class LlmClient
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /** @param array<string,mixed> $snapshot
     *  @return array{raw:string,used_fallback:bool,error:?string,model:?string,role:?string,base_url:?string}
     */
    public function call(array $snapshot): array
    {
        $selection = $this->selectModel($snapshot);
        $settings = $this->providerSettings();

        if ($settings['base_url'] === '' || $settings['token'] === '') {
            return [
                'raw' => $this->fallback($snapshot),
                'used_fallback' => true,
                'error' => 'openai_compatible base_url/token is not configured',
                'model' => $selection['model'],
                'role' => $selection['role'],
                'base_url' => $settings['base_url'],
            ];
        }

        $requestPayload = $this->buildRequestPayload($snapshot, $selection['model']);
        $response = $this->chatCompletionsRequest(
            $settings['base_url'],
            $settings['token'],
            $settings['timeout_seconds'],
            $requestPayload
        );

        if (($response['ok'] ?? false) !== true) {
            return [
                'raw' => $this->fallback($snapshot),
                'used_fallback' => true,
                'error' => (string)($response['error'] ?? 'openai-compatible request failed'),
                'model' => $selection['model'],
                'role' => $selection['role'],
                'base_url' => $settings['base_url'],
            ];
        }

        $raw = (string)($response['content'] ?? '');
        if (trim($raw) === '' || !$this->isValidDecisionShape($raw)) {
            return [
                'raw' => $this->fallback($snapshot),
                'used_fallback' => true,
                'error' => 'llm returned invalid JSON shape',
                'model' => $selection['model'],
                'role' => $selection['role'],
                'base_url' => $settings['base_url'],
            ];
        }

        return [
            'raw' => $raw,
            'used_fallback' => false,
            'error' => null,
            'model' => $selection['model'],
            'role' => $selection['role'],
            'base_url' => $settings['base_url'],
        ];
    }

    /** @param array<string,mixed> $snapshot
     *  @return array{role:string,model:string}
     */
    private function selectModel(array $snapshot): array
    {
        $map = $this->config->get('model_map', []);
        if (!is_array($map)) {
            $map = [];
        }
        $policy = $this->config->get('model_policy', []);
        if (!is_array($policy)) {
            $policy = [];
        }

        $role = (string)($policy['default_role'] ?? 'fast');

        $forcedRole = getenv('TOOLS_LLM_ROLE');
        if (is_string($forcedRole) && $forcedRole !== '' && isset($map[$forcedRole])) {
            $role = $forcedRole;
        } else {
            $caseType = (string)($snapshot['case_type'] ?? '');
            $status = (string)($snapshot['status'] ?? '');
            $adjudicatorCases = is_array($policy['adjudicator_case_types'] ?? null) ? $policy['adjudicator_case_types'] : [];
            $adjudicatorStatuses = is_array($policy['adjudicator_statuses'] ?? null) ? $policy['adjudicator_statuses'] : [];

            if (in_array($caseType, $adjudicatorCases, true) || in_array($status, $adjudicatorStatuses, true)) {
                $role = 'adjudicator';
            }
        }

        $model = isset($map[$role]) && is_string($map[$role]) ? $map[$role] : '';
        if ($model === '' && isset($map['fast']) && is_string($map['fast'])) {
            $role = 'fast';
            $model = $map['fast'];
        }
        if ($model === '') {
            $model = 'Qwen3-Coder-Next';
        }

        return ['role' => $role, 'model' => $model];
    }

    /** @return array{base_url:string,token:string,timeout_seconds:int} */
    private function providerSettings(): array
    {
        $provider = $this->config->get('openai_compatible', []);
        if (!is_array($provider)) {
            $provider = [];
        }

        $baseUrl = (string)($provider['base_url'] ?? '');
        $token = (string)($provider['token'] ?? '');

        $envBaseUrl = getenv('TOOLS_OPENAI_BASE_URL');
        $envToken = getenv('TOOLS_OPENAI_TOKEN');
        if (is_string($envBaseUrl) && $envBaseUrl !== '') {
            $baseUrl = $envBaseUrl;
        }
        if (is_string($envToken) && $envToken !== '') {
            $token = $envToken;
        }

        $timeout = (int)$this->config->get('llm_timeout_seconds', 60);
        if ($timeout <= 0) {
            $timeout = 60;
        }

        return [
            'base_url' => rtrim($baseUrl, '/'),
            'token' => $token,
            'timeout_seconds' => $timeout,
        ];
    }

    /**
     * @param array<string,mixed> $snapshot
     * @return array<string,mixed>
     */
    private function buildRequestPayload(array $snapshot, string $model): array
    {
        $system = [
            'role' => 'system',
            'content' => 'Return a strict JSON object only. No markdown. No explanations. Keep required keys.',
        ];

        $userPayload = [
            'task' => 'Decide one migration case and return strict JSON',
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
            'snapshot' => $snapshot,
        ];

        return [
            'model' => $model,
            'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                $system,
                [
                    'role' => 'user',
                    'content' => json_encode($userPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,content?:string,error?:string}
     */
    private function chatCompletionsRequest(string $baseUrl, string $token, int $timeout, array $payload): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'error' => 'curl extension is not available'];
        }

        $url = $baseUrl . '/chat/completions';
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'error' => 'cannot init curl'];
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            return ['ok' => false, 'error' => 'cannot encode request JSON'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        if (!is_string($response)) {
            return ['ok' => false, 'error' => $curlError !== '' ? $curlError : 'empty HTTP response'];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'provider returned non-JSON response'];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = (string)($decoded['error']['message'] ?? ('HTTP ' . $httpCode));
            return ['ok' => false, 'error' => $msg];
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;
        if (is_array($content)) {
            // Some providers return content parts.
            $parts = [];
            foreach ($content as $part) {
                if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                    $parts[] = $part['text'];
                }
            }
            $content = implode("\n", $parts);
        }

        if (!is_string($content) || trim($content) === '') {
            return ['ok' => false, 'error' => 'provider response has empty message content'];
        }

        return ['ok' => true, 'content' => $content];
    }

    /** @param array<string,mixed> $snapshot */
    private function fallback(array $snapshot): string
    {
        $subject = (string)($snapshot['subject_key'] ?? 'unknown');
        $caseType = (string)($snapshot['case_type'] ?? 'RELATION_CASE');
        $status = (string)($snapshot['status'] ?? 'GAP');
        $issueCode = $snapshot['issue_code'] ?? 'GENERIC_TOOL_LIMITATION';

        $payload = [
            'action' => 'NEED_MORE_USAGE',
            'case_type' => $caseType,
            'subject_key' => $subject,
            'status' => $status,
            'issue_code' => $issueCode,
            'confidence' => 0.25,
            'decision_basis' => ['YII', 'USAGE'],
            'requested_evidence' => ['USAGE_COLLECTION_SNIPPETS'],
            'candidate' => new \stdClass(),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private function isValidDecisionShape(string $raw): bool
    {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return false;
        }
        $required = [
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
        foreach ($required as $key) {
            if (!array_key_exists($key, $decoded)) {
                return false;
            }
        }
        return true;
    }
}

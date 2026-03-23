<?php

declare(strict_types=1);

namespace Tools;

final class Config
{
    private string $projectRoot;
    /** @var array<string,mixed> */
    private array $data;

    private function __construct(string $projectRoot, array $data)
    {
        $this->projectRoot = rtrim($projectRoot, '/');
        $this->data = $data;
    }

    public static function fromProjectRoot(string $projectRoot): self
    {
        $defaults = [
            'model_paths' => ['./app/models', './src'],
            'source_paths' => ['./app', './src'],
            'yii_model_namespaces' => ['App\\Model'],
            'uml_path' => './docs/domain.puml',
            'sql_dump_path' => './var/schema.sql',
            'analysis_dir' => './var/analysis',
            'trimmed_uml_dir' => './var/analysis/snapshots/_uml',
            'yii_bootstrap' => './protected/yii.php',
            'llm_timeout_seconds' => 60,
            'openai_compatible' => [
                'base_url' => 'http://127.0.0.1:11434/v1',
                'token' => '',
            ],
            'model_map' => [
                'retrieval' => 'qwen3-embedding:0.6b',
                'fast' => 'Qwen3-Coder-Next',
                'adjudicator' => 'gpt-oss-120b',
            ],
            'model_policy' => [
                'default_role' => 'fast',
                'adjudicator_case_types' => ['INHERITANCE_CASE', 'DISCRIMINATOR_CASE'],
                'adjudicator_statuses' => ['CONFLICT', 'MANUAL', 'UNSUPPORTED'],
            ],
        ];

        $configFile = rtrim($projectRoot, '/') . '/tools/config.php';
        $custom = [];
        if (is_file($configFile)) {
            $loaded = require $configFile;
            if (is_array($loaded)) {
                $custom = $loaded;
            }
        }

        return new self($projectRoot, array_replace($defaults, $custom));
    }

    /** @return mixed */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    public function resolvePath(string $path): string
    {
        if ($path === '') {
            return $this->projectRoot;
        }
        if ($path[0] === '/') {
            return $path;
        }
        return $this->projectRoot . '/' . ltrim($path, './');
    }

    /** @return list<string> */
    public function resolvePathList(string $key): array
    {
        $items = $this->get($key, []);
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $item) {
            if (is_string($item)) {
                $out[] = $this->resolvePath($item);
            }
        }
        return $out;
    }

    public function analysisDir(): string
    {
        $value = $this->get('analysis_dir', './var/analysis');
        return $this->resolvePath(is_string($value) ? $value : './var/analysis');
    }

    public function registryDir(): string
    {
        return $this->analysisDir() . '/registry';
    }

    public function snapshotsDir(): string
    {
        return $this->analysisDir() . '/snapshots';
    }

    public function llmResultsDir(): string
    {
        return $this->analysisDir() . '/llm-results';
    }

    public function reportsDir(): string
    {
        return $this->analysisDir() . '/reports';
    }

    public function trimmedUmlDir(): string
    {
        $value = $this->get('trimmed_uml_dir', './var/analysis/snapshots/_uml');
        return $this->resolvePath(is_string($value) ? $value : './var/analysis/snapshots/_uml');
    }

    public function ensureDirs(): void
    {
        $dirs = [
            $this->analysisDir(),
            $this->registryDir(),
            $this->snapshotsDir(),
            $this->llmResultsDir(),
            $this->reportsDir(),
            $this->snapshotsDir() . '/RELATION_CASE',
            $this->snapshotsDir() . '/INHERITANCE_CASE',
            $this->snapshotsDir() . '/DISCRIMINATOR_CASE',
            $this->snapshotsDir() . '/_usage',
            $this->trimmedUmlDir(),
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
    }
}

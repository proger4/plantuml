<?php

declare(strict_types=1);

namespace Tools\Yii;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tools\Config;

final class YiiMetaCollector
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /** @return array{entities:list<array<string,mixed>>,relations:list<array<string,mixed>>,inheritance:list<array<string,mixed>>,warnings:list<string>} */
    public function collect(string $root): array
    {
        $warnings = [];
        $classIndex = $this->buildClassIndex();

        if (!isset($classIndex[$root])) {
            $warnings[] = 'root class not found in model paths, synthetic metadata created';
            $classIndex[$root] = [
                'fqcn' => $root,
                'short' => $this->shortName($root),
                'namespace' => $this->namespaceOf($root),
                'parent' => null,
                'file' => null,
            ];
        }

        $descendants = $this->descendantsOf($root, $classIndex);
        if (!in_array($root, $descendants, true)) {
            $descendants[] = $root;
        }

        $entities = [];
        $relations = [];

        foreach ($descendants as $fqcn) {
            $meta = $classIndex[$fqcn] ?? null;
            $file = is_array($meta) ? ($meta['file'] ?? null) : null;
            $tableName = $this->guessTableName($this->shortName($fqcn));

            $entities[] = [
                'record_type' => 'ENTITY_RECORD',
                'subject_key' => $fqcn,
                'status' => 'GAP',
                'issue_code' => null,
                'confidence' => 0.45,
                'yii' => [
                    'class' => $fqcn,
                    'table_name' => $tableName,
                    'columns' => [],
                    'scopes' => [],
                    'source_file' => $file,
                ],
                'sql' => new \stdClass(),
                'usage' => new \stdClass(),
                'uml' => new \stdClass(),
            ];

            if (is_string($file) && is_file($file)) {
                foreach ($this->extractRelations($file, $fqcn, $root) as $rel) {
                    $relations[] = $rel;
                }
            }
        }

        $inheritanceStatus = count($descendants) > 1 ? 'OK' : 'GAP';
        $inheritanceIssue = count($descendants) > 1 ? null : 'INH_CHILD_SET_INCOMPLETE';

        $inheritance = [[
            'record_type' => 'INHERITANCE_RECORD',
            'subject_key' => $root,
            'status' => $inheritanceStatus,
            'issue_code' => $inheritanceIssue,
            'confidence' => count($descendants) > 1 ? 0.75 : 0.4,
            'class_tree' => array_values($descendants),
            'tables' => array_map(fn (array $e): string => (string)($e['yii']['table_name'] ?? ''), $entities),
            'usage' => new \stdClass(),
            'uml' => new \stdClass(),
        ]];

        return [
            'entities' => $entities,
            'relations' => $relations,
            'inheritance' => $inheritance,
            'warnings' => $warnings,
        ];
    }

    /** @return array<string,array<string,mixed>> */
    private function buildClassIndex(): array
    {
        $index = [];
        foreach ($this->phpFiles() as $file) {
            $content = (string)@file_get_contents($file);
            if ($content === '') {
                continue;
            }

            $namespace = '';
            if (preg_match('/namespace\s+([^;]+);/m', $content, $nm) === 1) {
                $namespace = trim($nm[1]);
            }

            if (preg_match('/class\s+([A-Za-z_][A-Za-z0-9_]*)\s*(?:extends\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*))?/m', $content, $cm) !== 1) {
                continue;
            }

            $short = $cm[1];
            $parentRaw = isset($cm[2]) ? trim($cm[2]) : null;
            $fqcn = $namespace !== '' ? $namespace . '\\' . $short : $short;

            $parent = null;
            if (is_string($parentRaw) && $parentRaw !== '') {
                if (str_contains($parentRaw, '\\')) {
                    $parent = ltrim($parentRaw, '\\');
                } elseif ($namespace !== '') {
                    $parent = $namespace . '\\' . $parentRaw;
                } else {
                    $parent = $parentRaw;
                }
            }

            $index[$fqcn] = [
                'fqcn' => $fqcn,
                'short' => $short,
                'namespace' => $namespace,
                'parent' => $parent,
                'file' => $file,
            ];
        }

        return $index;
    }

    /** @return list<string> */
    private function descendantsOf(string $root, array $index): array
    {
        $out = [$root];
        $changed = true;

        while ($changed) {
            $changed = false;
            foreach ($index as $fqcn => $meta) {
                $parent = $meta['parent'] ?? null;
                if (!is_string($parent) || $parent === '') {
                    continue;
                }
                if (in_array($parent, $out, true) && !in_array($fqcn, $out, true)) {
                    $out[] = $fqcn;
                    $changed = true;
                }
            }
        }

        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function extractRelations(string $file, string $fqcn, string $root): array
    {
        $content = (string)@file_get_contents($file);
        if ($content === '') {
            return [];
        }

        if (preg_match('/function\s+relations\s*\([^)]*\)\s*\{(.*?)\}/is', $content, $mm) !== 1) {
            return [];
        }

        $body = $mm[1];
        preg_match_all(
            '/[\'\"]([A-Za-z_][A-Za-z0-9_]*)[\'\"]\s*=>\s*array\s*\(\s*self::([A-Z_]+)\s*,\s*[\'\"]([^\'\"]+)[\'\"]\s*,\s*[\'\"]([^\'\"]+)[\'\"]/m',
            $body,
            $matches,
            PREG_SET_ORDER
        );

        $rows = [];
        foreach ($matches as $match) {
            $name = (string)$match[1];
            $type = (string)$match[2];
            $target = (string)$match[3];
            $fk = (string)$match[4];

            $rows[] = [
                'record_type' => 'RELATION_RECORD',
                'subject_key' => $fqcn . '::' . $name,
                'root_scope' => $root,
                'status' => 'GAP',
                'issue_code' => null,
                'confidence' => 0.5,
                'yii' => [
                    'owner' => $fqcn,
                    'relation_name' => $name,
                    'relation_type' => $type,
                    'target_class' => $target,
                    'fk' => $fk,
                    'source_file' => $file,
                ],
                'sql' => new \stdClass(),
                'usage' => new \stdClass(),
                'uml' => new \stdClass(),
            ];
        }

        return $rows;
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = [];
        foreach ($this->config->resolvePathList('model_paths') as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($it as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $name = $item->getFilename();
                if (str_ends_with($name, '.php')) {
                    $files[] = $item->getPathname();
                }
            }
        }
        sort($files);
        return $files;
    }

    private function guessTableName(string $short): string
    {
        $snake = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '_$0', $short));
        return $snake . 's';
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return (string)end($parts);
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        if ($pos === false) {
            return '';
        }
        return substr($fqcn, 0, $pos);
    }
}

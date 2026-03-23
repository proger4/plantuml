<?php

declare(strict_types=1);

namespace Tools\Usage;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tools\Config;
use Tools\SubjectKey;

final class UsageCollector
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /** @return array{patterns:array<string,int>,snippets:list<array<string,mixed>>,warnings:list<string>,scanned_files:int} */
    public function collect(string $root, ?string $subject, bool $deep): array
    {
        $relationName = $subject !== null ? SubjectKey::relationName($subject) : null;

        $patterns = [
            'singleton_hits' => 0,
            'collection_hits' => 0,
            'string_ref_hits' => 0,
            'criteria_hits' => 0,
            'inheritance_hits' => 0,
            'getter_conflict_hits' => 0,
        ];

        $snippets = [];
        $warnings = [];
        $limit = $deep ? 160 : 60;
        $scannedFiles = 0;

        foreach ($this->phpFiles() as $file) {
            $content = (string)@file_get_contents($file);
            if ($content === '') {
                continue;
            }
            $scannedFiles++;
            $lines = preg_split('/\R/', $content) ?: [];

            foreach ($lines as $idx => $line) {
                $lineNo = $idx + 1;

                foreach (UsagePatterns::all()['singleton'] as $rx) {
                    if (preg_match($rx, $line) === 1) {
                        $patterns['singleton_hits']++;
                        $this->pushSnippet($snippets, $limit, $file, $lineNo, 'singleton', $line, $relationName, $root, $deep);
                    }
                }

                foreach (UsagePatterns::all()['collection'] as $rx) {
                    if (preg_match($rx, $line) === 1) {
                        $patterns['collection_hits']++;
                        $this->pushSnippet($snippets, $limit, $file, $lineNo, 'collection', $line, $relationName, $root, $deep);
                    }
                }

                foreach (UsagePatterns::all()['string_relation'] as $rx) {
                    if (preg_match($rx, $line) === 1) {
                        $patterns['string_ref_hits']++;
                        $this->pushSnippet($snippets, $limit, $file, $lineNo, 'string_relation', $line, $relationName, $root, $deep);
                    }
                }

                foreach (UsagePatterns::all()['criteria'] as $rx) {
                    if (preg_match($rx, $line) === 1) {
                        $patterns['criteria_hits']++;
                        $this->pushSnippet($snippets, $limit, $file, $lineNo, 'criteria', $line, $relationName, $root, $deep);
                    }
                }

                foreach (UsagePatterns::all()['inheritance'] as $rx) {
                    if (preg_match($rx, $line) === 1) {
                        $patterns['inheritance_hits']++;
                        $this->pushSnippet($snippets, $limit, $file, $lineNo, 'inheritance', $line, $relationName, $root, $deep);
                    }
                }

                foreach (UsagePatterns::all()['getter_conflict'] as $rx) {
                    if (preg_match($rx, $line) === 1) {
                        $patterns['getter_conflict_hits']++;
                        $this->pushSnippet($snippets, $limit, $file, $lineNo, 'getter_conflict', $line, $relationName, $root, $deep);
                    }
                }
            }
        }

        if ($scannedFiles === 0) {
            $warnings[] = 'no PHP source files found for usage scan';
        }

        return [
            'patterns' => $patterns,
            'snippets' => $snippets,
            'warnings' => $warnings,
            'scanned_files' => $scannedFiles,
        ];
    }

    /** @return list<string> */
    private function phpFiles(): array
    {
        $files = [];
        $roots = array_merge(
            $this->config->resolvePathList('source_paths'),
            $this->config->resolvePathList('model_paths')
        );

        $roots = array_values(array_unique($roots));
        foreach ($roots as $path) {
            if (!is_dir($path)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
            foreach ($it as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                if (str_ends_with($item->getFilename(), '.php')) {
                    $files[] = $item->getPathname();
                }
            }
        }

        sort($files);
        return $files;
    }

    /** @param list<array<string,mixed>> $snippets */
    private function pushSnippet(
        array &$snippets,
        int $limit,
        string $file,
        int $line,
        string $category,
        string $text,
        ?string $relationName,
        string $root,
        bool $deep
    ): void {
        if (count($snippets) >= $limit) {
            return;
        }

        $focusMatch = true;
        if ($relationName !== null && $relationName !== '') {
            $focusMatch = str_contains($text, $relationName);
        }

        if (!$focusMatch && !$deep) {
            $rootShort = $this->shortName($root);
            $focusMatch = str_contains($text, $rootShort) || str_contains($text, $root);
        }

        if (!$focusMatch && !$deep) {
            return;
        }

        $snippets[] = [
            'file' => $file,
            'line' => $line,
            'category' => $category,
            'text' => trim($text),
        ];
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return (string)end($parts);
    }
}

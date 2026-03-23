<?php

declare(strict_types=1);

namespace Tools\Uml;

final class UmlTrimService
{
    /**
     * @param array{max_classes:int,max_relation_edges:int,max_hops:int} $limits
     * @return array{graph:array<string,mixed>,warnings:list<string>}
     */
    public function trim(string $umlPath, string $root, array $limits): array
    {
        $warnings = [];
        if (!is_file($umlPath)) {
            $warnings[] = 'UML file not found: ' . $umlPath;
            return [
                'graph' => [
                    'root' => $root,
                    'classes' => [$root],
                    'relation_edges' => [],
                    'inheritance_edges' => [],
                ],
                'warnings' => $warnings,
            ];
        }

        $lines = preg_split('/\R/', (string)file_get_contents($umlPath)) ?: [];
        $classes = [];
        $inheritanceEdges = [];
        $relationEdges = [];

        foreach ($lines as $lineNo => $line) {
            if (preg_match('/^\s*class\s+([A-Za-z_][A-Za-z0-9_\\\\]*)/m', $line, $cm) === 1) {
                $classes[$cm[1]] = true;
            }

            if (preg_match('/([A-Za-z_][A-Za-z0-9_\\\\]*)\s*<\|--\s*([A-Za-z_][A-Za-z0-9_\\\\]*)/', $line, $im) === 1) {
                $inheritanceEdges[] = ['parent' => $im[1], 'child' => $im[2], 'line' => $lineNo + 1];
                $classes[$im[1]] = true;
                $classes[$im[2]] = true;
            }

            if (preg_match('/([A-Za-z_][A-Za-z0-9_\\\\]*)\s+[-.o*]+>?<?[-.o*]+\s+([A-Za-z_][A-Za-z0-9_\\\\]*)/', $line, $rm) === 1) {
                $relationEdges[] = ['from' => $rm[1], 'to' => $rm[2], 'line' => $lineNo + 1];
                $classes[$rm[1]] = true;
                $classes[$rm[2]] = true;
            }
        }

        $selected = [$root => true];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($inheritanceEdges as $edge) {
                $p = (string)$edge['parent'];
                $c = (string)$edge['child'];
                if (isset($selected[$p]) && !isset($selected[$c])) {
                    $selected[$c] = true;
                    $changed = true;
                }
                if (isset($selected[$c]) && !isset($selected[$p])) {
                    $selected[$p] = true;
                    $changed = true;
                }
            }
        }

        $selectedRelations = [];
        $targets = [];
        foreach ($relationEdges as $edge) {
            $from = (string)$edge['from'];
            $to = (string)$edge['to'];
            if (isset($selected[$from]) || isset($selected[$to])) {
                $selectedRelations[] = $edge;
                $targets[$from] = true;
                $targets[$to] = true;
            }
        }

        foreach (array_keys($targets) as $target) {
            $selected[$target] = true;
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($inheritanceEdges as $edge) {
                $p = (string)$edge['parent'];
                $c = (string)$edge['child'];
                if (isset($selected[$p]) && !isset($selected[$c])) {
                    $selected[$c] = true;
                    $changed = true;
                }
                if (isset($selected[$c]) && !isset($selected[$p])) {
                    $selected[$p] = true;
                    $changed = true;
                }
            }
        }

        $selectedClasses = array_keys($selected);
        sort($selectedClasses);

        if (count($selectedClasses) > $limits['max_classes']) {
            $selectedClasses = array_slice($selectedClasses, 0, $limits['max_classes']);
            $warnings[] = 'class limit reached';
        }

        $selectedSet = array_fill_keys($selectedClasses, true);
        $selectedInheritance = array_values(array_filter(
            $inheritanceEdges,
            static fn (array $edge): bool => isset($selectedSet[$edge['parent']]) && isset($selectedSet[$edge['child']])
        ));
        $selectedRelations = array_values(array_filter(
            $selectedRelations,
            static fn (array $edge): bool => isset($selectedSet[$edge['from']]) && isset($selectedSet[$edge['to']])
        ));

        if (count($selectedRelations) > $limits['max_relation_edges']) {
            $selectedRelations = array_slice($selectedRelations, 0, $limits['max_relation_edges']);
            $warnings[] = 'relation edge limit reached';
        }

        return [
            'graph' => [
                'root' => $root,
                'classes' => $selectedClasses,
                'relation_edges' => $selectedRelations,
                'inheritance_edges' => $selectedInheritance,
            ],
            'warnings' => $warnings,
        ];
    }
}

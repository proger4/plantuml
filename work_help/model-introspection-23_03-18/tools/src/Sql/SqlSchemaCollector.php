<?php

declare(strict_types=1);

namespace Tools\Sql;

final class SqlSchemaCollector
{
    /** @return array{tables:array<string,mixed>,fks:list<array<string,mixed>>,junction_candidates:list<string>,discriminator_candidates:list<array<string,mixed>>,warnings:list<string>} */
    public function collect(string $sqlPath): array
    {
        $warnings = [];
        if (!is_file($sqlPath)) {
            $warnings[] = 'SQL dump not found: ' . $sqlPath;
            return [
                'tables' => [],
                'fks' => [],
                'junction_candidates' => [],
                'discriminator_candidates' => [],
                'warnings' => $warnings,
            ];
        }

        $content = (string)file_get_contents($sqlPath);
        if ($content === '') {
            $warnings[] = 'SQL dump is empty';
            return [
                'tables' => [],
                'fks' => [],
                'junction_candidates' => [],
                'discriminator_candidates' => [],
                'warnings' => $warnings,
            ];
        }

        preg_match_all('/CREATE\s+TABLE\s+`?([A-Za-z0-9_]+)`?\s*\((.*?)\)\s*;/is', $content, $matches, PREG_SET_ORDER);

        $tables = [];
        $fks = [];
        $junction = [];
        $discriminatorCandidates = [];

        foreach ($matches as $match) {
            $table = (string)$match[1];
            $body = (string)$match[2];
            $lines = preg_split('/\R/', $body) ?: [];

            $columns = [];
            $pk = [];
            $unique = [];
            $tableFks = [];

            foreach ($lines as $line) {
                $trim = trim($line, " \t\n\r,`'");
                if ($trim === '') {
                    continue;
                }

                if (preg_match('/^`?([A-Za-z0-9_]+)`?\s+([A-Za-z0-9()]+)/i', $trim, $cm) === 1) {
                    $column = $cm[1];
                    $columns[] = $column;
                    if (in_array(strtolower($column), ['type', 'dtype', 'discriminator', 'kind'], true)) {
                        $discriminatorCandidates[] = ['table' => $table, 'column' => $column];
                    }
                }

                if (preg_match('/PRIMARY\s+KEY\s*\(([^)]+)\)/i', $trim, $pm) === 1) {
                    $pk = array_map(static fn (string $x): string => trim($x, " `\t\n\r"), explode(',', $pm[1]));
                }

                if (preg_match('/UNIQUE\s+(?:KEY|INDEX)?\s*`?[A-Za-z0-9_]*`?\s*\(([^)]+)\)/i', $trim, $um) === 1) {
                    $unique[] = array_map(static fn (string $x): string => trim($x, " `\t\n\r"), explode(',', $um[1]));
                }

                if (preg_match('/FOREIGN\s+KEY\s*\(([^)]+)\)\s+REFERENCES\s+`?([A-Za-z0-9_]+)`?\s*\(([^)]+)\)/i', $trim, $fm) === 1) {
                    $fkCols = array_map(static fn (string $x): string => trim($x, " `\t\n\r"), explode(',', $fm[1]));
                    $refTable = trim($fm[2]);
                    $refCols = array_map(static fn (string $x): string => trim($x, " `\t\n\r"), explode(',', $fm[3]));
                    $fkRow = [
                        'table' => $table,
                        'columns' => $fkCols,
                        'ref_table' => $refTable,
                        'ref_columns' => $refCols,
                    ];
                    $tableFks[] = $fkRow;
                    $fks[] = $fkRow;
                }
            }

            if (count($tableFks) >= 2 && count($columns) <= 6) {
                $junction[] = $table;
            }

            $tables[$table] = [
                'columns' => $columns,
                'pk' => $pk,
                'unique' => $unique,
                'fks' => $tableFks,
            ];
        }

        return [
            'tables' => $tables,
            'fks' => $fks,
            'junction_candidates' => array_values(array_unique($junction)),
            'discriminator_candidates' => $discriminatorCandidates,
            'warnings' => $warnings,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Tools;

final class Jsonl
{
    /** @return list<array<string,mixed>> */
    public static function read(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $rows = [];
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return [];
        }
        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line == '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $rows[] = $decoded;
            }
        }
        fclose($fh);
        return $rows;
    }

    /** @param list<array<string,mixed>> $rows */
    public static function write(string $path, array $rows): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new \RuntimeException('cannot write jsonl: ' . $path);
        }
        foreach ($rows as $row) {
            fwrite($fh, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        }
        fclose($fh);
    }

    /** @param array<string,mixed> $row */
    public static function append(string $path, array $row): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $fh = fopen($path, 'a');
        if ($fh === false) {
            throw new \RuntimeException('cannot append jsonl: ' . $path);
        }
        fwrite($fh, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
        fclose($fh);
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>>|null $rows
     */
    public static function upsertBySubject(string $path, array $row, ?array $rows = null): void
    {
        $items = $rows ?? self::read($path);
        $subject = (string)($row['subject_key'] ?? '');
        $recordType = (string)($row['record_type'] ?? '');

        $updated = false;
        foreach ($items as $idx => $existing) {
            if (($existing['subject_key'] ?? null) === $subject && ($existing['record_type'] ?? null) === $recordType) {
                $items[$idx] = array_replace($existing, $row);
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $items[] = $row;
        }

        self::write($path, $items);
    }
}

<?php

declare(strict_types=1);

namespace Tools\Reconcile;

final class StatusDecider
{
    /** @param array<string,mixed> $record
     *  @return array{status:string,issue_code:?string,confidence:float,case_type:string}
     */
    public function decide(array $record): array
    {
        $recordType = (string)($record['record_type'] ?? '');
        if ($recordType === 'RELATION_RECORD') {
            return $this->decideRelation($record);
        }
        if ($recordType === 'INHERITANCE_RECORD') {
            return $this->decideInheritance($record);
        }
        if ($recordType === 'DISCRIMINATOR_RECORD') {
            return $this->decideDiscriminator($record);
        }

        return [
            'status' => 'UNSUPPORTED',
            'issue_code' => 'GENERIC_TOOL_LIMITATION',
            'confidence' => 0.2,
            'case_type' => 'INHERITANCE_CASE',
        ];
    }

    /** @param array<string,mixed> $record
     *  @return array{status:string,issue_code:?string,confidence:float,case_type:string}
     */
    private function decideRelation(array $record): array
    {
        $yii = is_array($record['yii'] ?? null) ? $record['yii'] : [];
        $usage = is_array($record['usage'] ?? null) ? $record['usage'] : [];
        $sql = is_array($record['sql'] ?? null) ? $record['sql'] : [];

        $type = strtoupper((string)($yii['relation_type'] ?? ''));
        $collectionHits = (int)($usage['patterns']['collection_hits'] ?? 0);
        $singletonHits = (int)($usage['patterns']['singleton_hits'] ?? 0);
        $fkCount = is_array($sql['fks'] ?? null) ? count($sql['fks']) : 0;

        if ($type === 'MANY_MANY') {
            return ['status' => 'DROP', 'issue_code' => 'REL_THROUGH', 'confidence' => 0.95, 'case_type' => 'RELATION_CASE'];
        }
        if ($type === 'STAT') {
            return ['status' => 'DROP', 'issue_code' => 'REL_STAT', 'confidence' => 0.95, 'case_type' => 'RELATION_CASE'];
        }
        if ($type === '') {
            return ['status' => 'GAP', 'issue_code' => 'REL_USAGE_GAP', 'confidence' => 0.42, 'case_type' => 'RELATION_CASE'];
        }
        if (in_array($type, ['BELONGS_TO', 'HAS_ONE'], true) && $collectionHits > 0) {
            return ['status' => 'CONFLICT', 'issue_code' => 'REL_TYPE_MISMATCH', 'confidence' => 0.86, 'case_type' => 'RELATION_CASE'];
        }
        if ($type === 'HAS_MANY' && $singletonHits > 0 && $collectionHits === 0) {
            return ['status' => 'CONFLICT', 'issue_code' => 'REL_TYPE_MISMATCH', 'confidence' => 0.72, 'case_type' => 'RELATION_CASE'];
        }
        if ($fkCount === 0) {
            return ['status' => 'GAP', 'issue_code' => 'REL_SQL_GAP', 'confidence' => 0.5, 'case_type' => 'RELATION_CASE'];
        }

        return ['status' => 'OK', 'issue_code' => null, 'confidence' => 0.8, 'case_type' => 'RELATION_CASE'];
    }

    /** @param array<string,mixed> $record
     *  @return array{status:string,issue_code:?string,confidence:float,case_type:string}
     */
    private function decideInheritance(array $record): array
    {
        $tree = is_array($record['class_tree'] ?? null) ? $record['class_tree'] : [];
        if (count($tree) <= 0) {
            return ['status' => 'GAP', 'issue_code' => 'INH_ROOT_AMBIGUOUS', 'confidence' => 0.35, 'case_type' => 'INHERITANCE_CASE'];
        }
        if (count($tree) === 1) {
            return ['status' => 'GAP', 'issue_code' => 'INH_CHILD_SET_INCOMPLETE', 'confidence' => 0.44, 'case_type' => 'INHERITANCE_CASE'];
        }
        return ['status' => 'OK', 'issue_code' => null, 'confidence' => 0.78, 'case_type' => 'INHERITANCE_CASE'];
    }

    /** @param array<string,mixed> $record
     *  @return array{status:string,issue_code:?string,confidence:float,case_type:string}
     */
    private function decideDiscriminator(array $record): array
    {
        $columns = is_array($record['candidate_columns'] ?? null) ? $record['candidate_columns'] : [];
        $values = is_array($record['candidate_values'] ?? null) ? $record['candidate_values'] : [];
        $usage = is_array($record['usage'] ?? null) ? $record['usage'] : [];

        $inhHits = (int)($usage['patterns']['inheritance_hits'] ?? 0);

        if (count($columns) === 0) {
            return ['status' => 'GAP', 'issue_code' => 'DISC_COLUMN_MISSING', 'confidence' => 0.4, 'case_type' => 'DISCRIMINATOR_CASE'];
        }
        if (count($values) === 0 && $inhHits > 0) {
            return ['status' => 'CONFLICT', 'issue_code' => 'DISC_VALUE_CONFLICT', 'confidence' => 0.58, 'case_type' => 'DISCRIMINATOR_CASE'];
        }
        if (count($values) === 0) {
            return ['status' => 'GAP', 'issue_code' => 'DISC_MAP_INCOMPLETE', 'confidence' => 0.46, 'case_type' => 'DISCRIMINATOR_CASE'];
        }

        return ['status' => 'OK', 'issue_code' => null, 'confidence' => 0.74, 'case_type' => 'DISCRIMINATOR_CASE'];
    }
}

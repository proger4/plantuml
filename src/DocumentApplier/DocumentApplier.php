<?php
declare(strict_types=1);

namespace App\DocumentApplier;

use App\Domain\ValueObject\TextChange;

/**
 * DocumentApplier = core brick (c2).
 *
 * Minimum semantics:
 * - insert/replace applied over UTF-8 indices
 * - caret normalization (simple)
 *
 * TODO (you explicitly wanted):
 * - support granular edits from UI:
 *   - selection delete/backspace
 *   - multi-caret (optional)
 *   - diff-to-change converter in frontend
 * - add invariants: never break @startuml/@enduml (optional policy)
 */
final class DocumentApplier
{
    private ChangeValidator $validator;

    public function __construct()
    {
        $this->validator = new ChangeValidator();
    }

    public function apply(string $code, TextChange $change): AppliedChange
    {
        $this->validator->validate($code, $change);

        $newCode = Operations::apply($code, $change);

        $caretLeft = $change->range->left + StringOps::len($change->text);
        $caretRight = $caretLeft;

        return new AppliedChange($newCode, $caretLeft, $caretRight);
    }
}

final class AppliedChange
{
    public function __construct(
        public readonly string $code,
        public readonly int    $caretLeft,
        public readonly int    $caretRight,
    )
    {
    }
}

List unresolved model introspection issues.

Args: {{args}}

Instructions:
- Read existing registry only.
- Run deterministic wrapper only:

python .roo/scripts/roo_registry.py list-issues {{args}}

- Default statuses: GAP, CONFLICT, MANUAL.

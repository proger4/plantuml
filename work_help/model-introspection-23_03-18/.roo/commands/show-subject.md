Show one subject card from introspection artifacts.

Args: {{args}}

Instructions:
- Resolve subject strictly by exact `subject_key`.
- Run deterministic wrapper only:

python .roo/scripts/roo_subject.py show-subject {{args}}

- If not found, return `ERROR: subject not found`.

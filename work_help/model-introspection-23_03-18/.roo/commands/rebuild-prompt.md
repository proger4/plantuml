Rebuild one snapshot/prompt for a single subject.

Args: {{args}}

Instructions:
- Run only build-prompt for one subject.
- Do not trigger reconcile/call-llm/report.
- Run deterministic wrapper only:

python .roo/scripts/roo_subject.py rebuild-prompt {{args}}

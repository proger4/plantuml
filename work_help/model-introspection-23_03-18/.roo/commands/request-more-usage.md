Request deeper usage evidence for one subject.

Args: {{args}}

Instructions:
- Run only targeted collect-usage step.
- Do not trigger reconcile/build-prompt/call-llm/report.
- Run deterministic wrapper only:

python .roo/scripts/roo_subject.py request-more-usage {{args}}

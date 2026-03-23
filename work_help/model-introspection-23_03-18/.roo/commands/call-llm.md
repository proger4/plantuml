Call local LLM for one ready snapshot.

Args: {{args}}

Instructions:
- Accept only `--subject` or `--snapshot`.
- Do not build prompt automatically.
- Validate JSON structure via wrapper validation step.
- Run deterministic wrapper only:

python .roo/scripts/roo_subject.py call-llm {{args}}

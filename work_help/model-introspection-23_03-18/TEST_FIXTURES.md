# Test Fixtures For Offline Pipeline Checks

This folder contains a fully synthetic legacy-like setup so the pipeline can be tested without real project integration.

## Included mismatches

- `Profile::user` declared as `BELONGS_TO` while usage is collection-like (`foreach ($profile->user as ...)`).
- SQL relation mismatch (`profiles.user_ref` exists, but relation expects `user_id` and no FK for it).
- Inheritance/discriminator evidence exists in usage (`switch ($profile->type)`, `createByType`) with limited schema mapping.
- `MANY_MANY` and `STAT` relations included to exercise DROP classification paths.

## Quick run

```bash
python3 .roo/scripts/roo_preflight.py
python3 .roo/scripts/roo_run_scope.py 'App\\Model\\Profile'
python3 .roo/scripts/roo_registry.py list-issues --root 'App\\Model\\Profile'
python3 .roo/scripts/roo_subject.py show-subject 'App\\Model\\Profile::user'
python3 .roo/scripts/roo_subject.py rebuild-prompt --subject 'App\\Model\\Profile::user'
python3 .roo/scripts/roo_subject.py call-llm --subject 'App\\Model\\Profile::user'
python3 .roo/scripts/roo_registry.py report --root 'App\\Model\\Profile'
```

If local OpenAI-compatible LLM is not reachable, `call-llm` falls back to deterministic JSON response by design.

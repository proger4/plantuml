# Roo Model Introspection Control Plane (v1)

This `.roo` layer is a deterministic CLI control plane for model-introspection pipeline tasks.

## What `.roo` does

- validates environment and required files;
- runs fixed PHP pipeline steps in order;
- stores minimal run metadata;
- lists unresolved issues from registry;
- navigates by exact `subject_key`;
- triggers focused usage refresh / snapshot rebuild;
- calls local LLM on an existing snapshot and validates JSON keys;
- runs report generation by root or subject.

## What `.roo` does not do

- no domain reconcile logic;
- no code analysis beyond file-level lookup and filtering;
- no prompt semantics generation;
- no hidden batch orchestration, queue, daemon, watcher;
- no fuzzy subject guessing.

## Commands

- `/preflight`
- `/run-scope <root>`
- `/list-issues`
- `/show-subject <subject_key>`
- `/request-more-usage --subject <subject_key>`
- `/rebuild-prompt --subject <subject_key>`
- `/call-llm (--subject <subject_key> | --snapshot <file>)`
- `/report (--root <FQCN> | --subject <subject_key>)`

## Typical workflow

```text
/preflight
/run-scope App\Model\Profile
/list-issues --root App\Model\Profile
/show-subject App\Model\Profile::user
/request-more-usage --subject App\Model\Profile::user
/rebuild-prompt --subject App\Model\Profile::user
/call-llm --subject App\Model\Profile::user
/report --root App\Model\Profile
```

## Run metadata

Each scope run writes:

- `./.roo/runs/<run_id>/run.json`
- `./.roo/runs/<run_id>/steps.jsonl`
- `./.roo/runs/<run_id>/artifacts.json`

## Config files

- `./.roo/config/pipeline.yaml`
- `./.roo/config/llm.yaml`
- `./.roo/config/paths.yaml`

Config files use JSON-compatible YAML subset so Python stdlib parser can be used without external packages.

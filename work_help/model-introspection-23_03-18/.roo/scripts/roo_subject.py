#!/usr/bin/env python
from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path


def project_root() -> Path:
    return Path(__file__).resolve().parents[2]


def load_config(path: Path) -> dict:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        raise RuntimeError(f"missing config: {path}")
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"invalid config format: {path} ({exc})")


def as_abs(root: Path, value: str) -> Path:
    p = Path(value)
    if p.is_absolute():
        return p
    return (root / p).resolve()


def read_jsonl(path: Path) -> list[dict]:
    if not path.exists():
        return []
    out: list[dict] = []
    with path.open("r", encoding="utf-8") as fh:
        for line in fh:
            text = line.strip()
            if not text:
                continue
            try:
                obj = json.loads(text)
            except json.JSONDecodeError:
                continue
            if isinstance(obj, dict):
                out.append(obj)
    return out


def find_latest_json_by_subject(base_dir: Path, subject_key: str) -> str | None:
    if not base_dir.exists():
        return None
    candidates: list[Path] = []
    for p in base_dir.glob("**/*.json"):
        if not p.is_file():
            continue
        try:
            payload = json.loads(p.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            continue
        if isinstance(payload, dict) and payload.get("subject_key") == subject_key:
            candidates.append(p)
    if not candidates:
        return None
    latest = max(candidates, key=lambda x: x.stat().st_mtime)
    return str(latest)


def find_report_for_subject(base_dir: Path, subject_key: str) -> str | None:
    if not base_dir.exists():
        return None
    candidates: list[Path] = []
    fallbacks: list[Path] = []
    for p in base_dir.glob("**/*.json"):
        if not p.is_file():
            continue
        fallbacks.append(p)
        try:
            payload = json.loads(p.read_text(encoding="utf-8"))
        except Exception:  # noqa: BLE001
            continue
        if not isinstance(payload, dict):
            continue
        unresolved = payload.get("unresolved")
        if not isinstance(unresolved, list):
            continue
        for row in unresolved:
            if isinstance(row, dict) and row.get("subject_key") == subject_key:
                candidates.append(p)
                break
    if candidates:
        latest = max(candidates, key=lambda x: x.stat().st_mtime)
        return str(latest)
    if fallbacks:
        latest = max(fallbacks, key=lambda x: x.stat().st_mtime)
        return str(latest)
    return None


def case_type_from_record(record: dict) -> str:
    if isinstance(record.get("case_type"), str):
        return str(record["case_type"])
    record_type = str(record.get("record_type", ""))
    if record_type == "RELATION_RECORD":
        return "RELATION_CASE"
    if record_type == "INHERITANCE_RECORD":
        return "INHERITANCE_CASE"
    if record_type == "DISCRIMINATOR_RECORD":
        return "DISCRIMINATOR_CASE"
    return ""


def find_subject_card(subject_key: str, paths_cfg: dict, root: Path) -> dict | None:
    registry = paths_cfg.get("registry", {}) if isinstance(paths_cfg.get("registry"), dict) else {}
    files = []
    for key in ["issues", "relations", "inheritance", "discriminators", "entities"]:
        value = registry.get(key)
        if isinstance(value, str):
            files.append(as_abs(root, value))

    found = None
    for file_path in files:
        for row in read_jsonl(file_path):
            if str(row.get("subject_key", "")) == subject_key:
                found = row
                if str(row.get("record_type", "")) == "ISSUE_RECORD":
                    break
        if found and str(found.get("record_type", "")) == "ISSUE_RECORD":
            break

    if not found:
        return None

    snapshots_dir = as_abs(root, str(paths_cfg.get("snapshots", {}).get("dir", "./var/analysis/snapshots")))
    llm_dir = as_abs(root, str(paths_cfg.get("llm", {}).get("results_dir", "./var/analysis/llm-results")))
    reports_dir = as_abs(root, str(paths_cfg.get("reports", {}).get("dir", "./var/analysis/reports")))

    return {
        "subject_key": subject_key,
        "status": found.get("status"),
        "issue_code": found.get("issue_code"),
        "case_type": case_type_from_record(found),
        "snapshot_path": find_latest_json_by_subject(snapshots_dir, subject_key),
        "llm_result_path": find_latest_json_by_subject(llm_dir, subject_key),
        "report_path": find_report_for_subject(reports_dir, subject_key),
    }


def run_php(cmd: list[str], root: Path, verbose: bool) -> subprocess.CompletedProcess[str]:
    proc = subprocess.run(cmd, cwd=root, capture_output=True, text=True, check=False)
    if verbose:
        print(f"$ {' '.join(cmd)}", file=sys.stderr)
        if proc.stdout.strip():
            print(proc.stdout.rstrip(), file=sys.stderr)
        if proc.stderr.strip():
            print(proc.stderr.rstrip(), file=sys.stderr)
    return proc


def find_snapshot_for_subject(root: Path, paths_cfg: dict, subject_key: str) -> str | None:
    snap_dir = as_abs(root, str(paths_cfg.get("snapshots", {}).get("dir", "./var/analysis/snapshots")))
    return find_latest_json_by_subject(snap_dir, subject_key)


def cmd_show_subject(args: argparse.Namespace, root: Path, paths_cfg: dict) -> int:
    card = find_subject_card(args.subject_key, paths_cfg, root)
    if not card:
        print("ERROR: subject not found")
        return 1

    if args.format == "json":
        print(json.dumps(card, ensure_ascii=False, indent=2))
    else:
        print(f"subject_key: {card['subject_key']}")
        print(f"case_type: {card['case_type']}")
        print(f"status: {card['status']}")
        print(f"issue_code: {card['issue_code']}")
        print(f"snapshot: {card['snapshot_path']}")
        print(f"llm_result: {card['llm_result_path']}")
        print(f"report: {card['report_path']}")
    return 0


def cmd_request_more_usage(args: argparse.Namespace, root: Path, pipeline: dict) -> int:
    php_bin = str(pipeline.get("php_bin", "php8"))
    tools_bin_dir = as_abs(root, str(pipeline.get("tools_bin_dir", "./tools/bin")))
    cmd = [php_bin, str(tools_bin_dir / "collect-usage.php"), "--subject", args.subject, "--deep"]
    proc = run_php(cmd, root, args.verbose)
    payload = {
        "subject": args.subject,
        "step": "collect-usage",
        "mode": "deep",
        "exit_status": proc.returncode,
    }
    if args.format == "json":
        print(json.dumps(payload, ensure_ascii=False, indent=2))
    else:
        print(f"subject: {args.subject}")
        print("step: collect-usage")
        print("mode: deep")
        print(f"exit: {proc.returncode}")
    return 0 if proc.returncode == 0 else proc.returncode


def cmd_rebuild_prompt(args: argparse.Namespace, root: Path, pipeline: dict, paths_cfg: dict) -> int:
    php_bin = str(pipeline.get("php_bin", "php8"))
    tools_bin_dir = as_abs(root, str(pipeline.get("tools_bin_dir", "./tools/bin")))
    cmd = [php_bin, str(tools_bin_dir / "build-prompt.php"), "--subject", args.subject]
    proc = run_php(cmd, root, args.verbose)
    snapshot = find_snapshot_for_subject(root, paths_cfg, args.subject)
    payload = {
        "subject": args.subject,
        "prompt_rebuilt": proc.returncode == 0,
        "snapshot_path": snapshot,
        "exit_status": proc.returncode,
    }
    if args.format == "json":
        print(json.dumps(payload, ensure_ascii=False, indent=2))
    else:
        print(f"subject: {args.subject}")
        print(f"prompt rebuilt: {'yes' if proc.returncode == 0 else 'no'}")
        print(f"snapshot: {snapshot}")
    return 0 if proc.returncode == 0 else proc.returncode


def cmd_call_llm(args: argparse.Namespace, root: Path, pipeline: dict, paths_cfg: dict) -> int:
    php_bin = str(pipeline.get("php_bin", "php8"))
    python_bin = str(pipeline.get("python_bin", "python"))
    tools_bin_dir = as_abs(root, str(pipeline.get("tools_bin_dir", "./tools/bin")))

    subject = args.subject
    snapshot = args.snapshot
    if subject:
        snapshot = find_snapshot_for_subject(root, paths_cfg, subject)
        if not snapshot:
            print("ERROR: snapshot not found for subject")
            return 1

    assert snapshot is not None
    cmd = [php_bin, str(tools_bin_dir / "call-llm.php"), "--snapshot", str(snapshot), "--format", "json"]
    proc = run_php(cmd, root, args.verbose)
    if proc.returncode != 0:
        print((proc.stderr or proc.stdout).strip() or "ERROR: call-llm failed")
        return proc.returncode

    try:
        call_payload = json.loads(proc.stdout)
    except json.JSONDecodeError:
        print("ERROR: invalid JSON from call-llm.php")
        return 1

    parsed_path = call_payload.get("parsed_response_file")
    if not isinstance(parsed_path, str):
        print("ERROR: parsed_response_file missing")
        return 1

    validate_cmd = [python_bin, str(root / ".roo/scripts/roo_validate_llm_json.py"), parsed_path, "--format", "json"]
    vproc = run_php(validate_cmd, root, args.verbose)
    validation_ok = vproc.returncode == 0
    validation_payload = None
    if vproc.stdout.strip():
        try:
            validation_payload = json.loads(vproc.stdout)
        except json.JSONDecodeError:
            validation_payload = None

    out = {
        "subject": subject,
        "snapshot": snapshot,
        "llm_result_path": parsed_path,
        "validation": "OK" if validation_ok else "ERROR",
        "validation_details": validation_payload,
    }

    if args.format == "json":
        print(json.dumps(out, ensure_ascii=False, indent=2))
    else:
        print(f"subject: {subject}")
        print(f"snapshot: {snapshot}")
        print(f"llm result: {parsed_path}")
        print(f"validation: {out['validation']}")

    return 0 if validation_ok else 1


def main() -> int:
    parser = argparse.ArgumentParser(description="Subject-level deterministic wrapper")
    sub = parser.add_subparsers(dest="action", required=True)

    p_show = sub.add_parser("show-subject")
    p_show.add_argument("subject_key")
    p_show.add_argument("--format", choices=["table", "json"], default="table")
    p_show.add_argument("--verbose", action="store_true")

    p_more = sub.add_parser("request-more-usage")
    p_more.add_argument("--subject", required=True)
    p_more.add_argument("--format", choices=["table", "json"], default="table")
    p_more.add_argument("--verbose", action="store_true")

    p_rebuild = sub.add_parser("rebuild-prompt")
    p_rebuild.add_argument("--subject", required=True)
    p_rebuild.add_argument("--format", choices=["table", "json"], default="table")
    p_rebuild.add_argument("--verbose", action="store_true")

    p_call = sub.add_parser("call-llm")
    group = p_call.add_mutually_exclusive_group(required=True)
    group.add_argument("--subject")
    group.add_argument("--snapshot")
    p_call.add_argument("--format", choices=["table", "json"], default="table")
    p_call.add_argument("--verbose", action="store_true")

    args = parser.parse_args()
    root = project_root()

    try:
        pipeline = load_config(root / ".roo/config/pipeline.yaml")
        paths_cfg = load_config(root / ".roo/config/paths.yaml")
    except RuntimeError as exc:
        print(f"ERROR: {exc}")
        return 1

    if args.action == "show-subject":
        return cmd_show_subject(args, root, paths_cfg)
    if args.action == "request-more-usage":
        return cmd_request_more_usage(args, root, pipeline)
    if args.action == "rebuild-prompt":
        return cmd_rebuild_prompt(args, root, pipeline, paths_cfg)
    return cmd_call_llm(args, root, pipeline, paths_cfg)


if __name__ == "__main__":
    raise SystemExit(main())

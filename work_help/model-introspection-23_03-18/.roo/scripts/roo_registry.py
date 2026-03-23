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
    rows: list[dict] = []
    if not path.exists():
        return rows
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
                rows.append(obj)
    return rows


def root_matches(subject_key: str, root: str) -> bool:
    return subject_key == root or subject_key.startswith(root + "::")


def print_table(rows: list[dict], columns: list[str]) -> None:
    widths = {c: len(c) for c in columns}
    for row in rows:
        for col in columns:
            widths[col] = max(widths[col], len(str(row.get(col, ""))))
    sep = " | "
    print(sep.join(col.ljust(widths[col]) for col in columns))
    print("-+-".join("-" * widths[c] for c in columns))
    for row in rows:
        print(sep.join(str(row.get(col, "")).ljust(widths[col]) for col in columns))


def cmd_list_issues(args: argparse.Namespace) -> int:
    root = project_root()
    try:
        pipeline = load_config(root / ".roo/config/pipeline.yaml")
        paths = load_config(root / ".roo/config/paths.yaml")
    except RuntimeError as exc:
        print(f"ERROR: {exc}")
        return 1

    registry = paths.get("registry", {}) if isinstance(paths.get("registry"), dict) else {}
    issues_path = as_abs(root, str(registry.get("issues", "./var/analysis/registry/issues.jsonl")))

    rows = read_jsonl(issues_path)

    statuses = args.status or list(pipeline.get("default_unresolved_statuses", ["GAP", "CONFLICT", "MANUAL"]))
    filtered = []
    for row in rows:
        status = str(row.get("status", ""))
        subject_key = str(row.get("subject_key", ""))
        if statuses and status not in statuses:
            continue
        if args.root and not root_matches(subject_key, args.root):
            continue
        if args.subject and subject_key != args.subject:
            continue
        filtered.append(row)

    if args.format == "json":
        print(json.dumps(filtered, ensure_ascii=False, indent=2))
    else:
        view = [
            {
                "subject_key": r.get("subject_key", ""),
                "case_type": r.get("case_type", ""),
                "status": r.get("status", ""),
                "issue_code": r.get("issue_code", ""),
            }
            for r in filtered
        ]
        if view:
            print_table(view, ["subject_key", "case_type", "status", "issue_code"])
        else:
            print("No issues found.")
    return 0


def cmd_report(args: argparse.Namespace) -> int:
    root = project_root()
    try:
        pipeline = load_config(root / ".roo/config/pipeline.yaml")
    except RuntimeError as exc:
        print(f"ERROR: {exc}")
        return 1

    php_bin = str(pipeline.get("php_bin", "php8"))
    tools_bin_dir = as_abs(root, str(pipeline.get("tools_bin_dir", "./tools/bin")))
    cmd = [php_bin, str(tools_bin_dir / "report.php"), "--format", "json"]
    if args.root:
        cmd += ["--root", args.root]
    else:
        cmd += ["--subject", args.subject]

    proc = subprocess.run(cmd, cwd=root, capture_output=True, text=True, check=False)
    if args.verbose:
        print(f"$ {' '.join(cmd)}", file=sys.stderr)
        if proc.stdout.strip():
            print(proc.stdout.rstrip(), file=sys.stderr)
        if proc.stderr.strip():
            print(proc.stderr.rstrip(), file=sys.stderr)

    if proc.returncode != 0:
        print((proc.stderr or proc.stdout).strip() or "ERROR: report failed")
        return proc.returncode

    try:
        payload = json.loads(proc.stdout)
    except json.JSONDecodeError:
        print("ERROR: invalid JSON from report.php")
        return 1

    if args.format == "json":
        print(json.dumps(payload, ensure_ascii=False, indent=2))
    else:
        target = args.root if args.root else args.subject
        print(f"target: {target}")
        print(f"status: {'OK' if payload.get('ok', False) else 'ERROR'}")
        print(f"report: {payload.get('report_path')}")
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Registry and report wrapper")
    sub = parser.add_subparsers(dest="action", required=True)

    p_list = sub.add_parser("list-issues")
    p_list.add_argument("--root")
    p_list.add_argument("--subject")
    p_list.add_argument("--status", action="append", choices=["OK", "GAP", "CONFLICT", "MANUAL", "DROP", "UNSUPPORTED"])
    p_list.add_argument("--format", choices=["table", "json"], default="table")
    p_list.add_argument("--verbose", action="store_true")

    p_report = sub.add_parser("report")
    target = p_report.add_mutually_exclusive_group(required=True)
    target.add_argument("--root")
    target.add_argument("--subject")
    p_report.add_argument("--format", choices=["table", "json"], default="table")
    p_report.add_argument("--verbose", action="store_true")

    args = parser.parse_args()
    if args.action == "list-issues":
        return cmd_list_issues(args)
    return cmd_report(args)


if __name__ == "__main__":
    raise SystemExit(main())

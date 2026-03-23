#!/usr/bin/env python
from __future__ import annotations

import argparse
import json
import subprocess
import uuid
from datetime import datetime, timezone
from pathlib import Path


def now_iso() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


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


def write_json(path: Path, payload: dict) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def write_jsonl(path: Path, rows: list[dict]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8") as fh:
        for row in rows:
            fh.write(json.dumps(row, ensure_ascii=False) + "\n")


def find_latest_file(path: Path) -> str | None:
    if not path.exists() or not path.is_dir():
        return None
    files = [p for p in path.glob("**/*") if p.is_file()]
    if not files:
        return None
    latest = max(files, key=lambda p: p.stat().st_mtime)
    return str(latest)


def main() -> int:
    parser = argparse.ArgumentParser(description="Run deterministic introspection pipeline for one root")
    parser.add_argument("root")
    parser.add_argument("--format", choices=["table", "json"], default="table")
    parser.add_argument("--verbose", action="store_true")
    args = parser.parse_args()

    root = project_root()
    try:
        pipeline = load_config(root / ".roo/config/pipeline.yaml")
        paths_cfg = load_config(root / ".roo/config/paths.yaml")
    except RuntimeError as exc:
        print(f"ERROR: {exc}")
        return 1

    php_bin = str(pipeline.get("php_bin", "php8"))
    tools_bin_dir = as_abs(root, str(pipeline.get("tools_bin_dir", "./tools/bin")))
    runs_dir = as_abs(root, str(pipeline.get("runs_dir", "./.roo/runs")))

    run_id = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ") + "-" + uuid.uuid4().hex[:8]
    run_dir = runs_dir / run_id
    run_dir.mkdir(parents=True, exist_ok=True)

    started_at = now_iso()
    step_specs = [
        ("collect-yii-meta", [php_bin, str(tools_bin_dir / "collect-yii-meta.php"), "--root", args.root]),
        ("collect-sql-meta", [php_bin, str(tools_bin_dir / "collect-sql-meta.php"), "--root", args.root]),
        ("collect-usage", [php_bin, str(tools_bin_dir / "collect-usage.php"), "--root", args.root]),
        ("collect-uml-meta", [php_bin, str(tools_bin_dir / "collect-uml-meta.php"), "--root", args.root]),
        ("reconcile", [php_bin, str(tools_bin_dir / "reconcile.php"), "--root", args.root]),
        ("build-prompt", [php_bin, str(tools_bin_dir / "build-prompt.php"), "--root", args.root]),
        ("report", [php_bin, str(tools_bin_dir / "report.php"), "--root", args.root, "--format", "json"]),
    ]

    steps: list[dict] = []
    report_stdout = ""

    for step_name, cmd in step_specs:
        st = now_iso()
        proc = subprocess.run(cmd, cwd=root, capture_output=True, text=True, check=False)
        ft = now_iso()
        status = "OK" if proc.returncode == 0 else "FAILED"
        row = {
            "step": step_name,
            "command": cmd,
            "started_at": st,
            "finished_at": ft,
            "exit_code": proc.returncode,
            "status": status,
        }
        if args.verbose:
            row["stdout"] = proc.stdout
            row["stderr"] = proc.stderr
        steps.append(row)

        if args.verbose:
            print(f"$ {' '.join(cmd)}")
            if proc.stdout.strip():
                print(proc.stdout.rstrip())
            if proc.stderr.strip():
                print(proc.stderr.rstrip())

        if step_name == "report":
            report_stdout = proc.stdout

        if proc.returncode != 0:
            break

    finished_at = now_iso()
    failed = sum(1 for s in steps if s["status"] == "FAILED")
    ok_steps = sum(1 for s in steps if s["status"] == "OK")

    run_payload = {
        "run_id": run_id,
        "root": args.root,
        "started_at": started_at,
        "finished_at": finished_at,
        "status": "FAILED" if failed else "OK",
    }

    report_path = None
    if report_stdout.strip():
        try:
            parsed = json.loads(report_stdout)
            if isinstance(parsed, dict):
                report_path = parsed.get("report_path")
        except json.JSONDecodeError:
            report_path = None

    paths_registry = paths_cfg.get("registry", {}) if isinstance(paths_cfg.get("registry"), dict) else {}
    issues_path = as_abs(root, str(paths_registry.get("issues", "./var/analysis/registry/issues.jsonl")))
    snapshots_dir = as_abs(root, str(paths_cfg.get("snapshots", {}).get("dir", "./var/analysis/snapshots")))
    reports_dir = as_abs(root, str(paths_cfg.get("reports", {}).get("dir", "./var/analysis/reports")))

    artifacts = {
        "registry_path": str(issues_path.parent) if issues_path.parent.exists() else None,
        "issues_path": str(issues_path) if issues_path.exists() else None,
        "snapshot_root_path": str(snapshots_dir) if snapshots_dir.exists() else None,
        "report_path": report_path or find_latest_file(reports_dir),
    }

    write_json(run_dir / "run.json", run_payload)
    write_jsonl(run_dir / "steps.jsonl", steps)
    write_json(run_dir / "artifacts.json", artifacts)

    summary = {
        "run_id": run_id,
        "root": args.root,
        "steps_ok": ok_steps,
        "steps_failed": failed,
        "report_path": artifacts["report_path"],
        "issues_path": artifacts["issues_path"],
        "run_path": str(run_dir),
        "status": run_payload["status"],
    }

    if args.format == "json":
        print(json.dumps(summary, ensure_ascii=False, indent=2))
    else:
        print(f"run_id: {summary['run_id']}")
        print(f"root: {summary['root']}")
        print(f"steps OK: {summary['steps_ok']}")
        print(f"steps FAILED: {summary['steps_failed']}")
        print(f"report: {summary['report_path']}")
        print(f"issues: {summary['issues_path']}")
        print(f"run_dir: {summary['run_path']}")

    return 0 if failed == 0 else 1


if __name__ == "__main__":
    raise SystemExit(main())

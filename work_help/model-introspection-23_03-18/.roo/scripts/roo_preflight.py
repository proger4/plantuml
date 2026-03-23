#!/usr/bin/env python
from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path


TOOLS_REQUIRED = [
    "collect-yii-meta.php",
    "collect-sql-meta.php",
    "collect-usage.php",
    "collect-uml-meta.php",
    "reconcile.php",
    "build-prompt.php",
    "call-llm.php",
    "report.php",
]


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


def check_rw_dir(path: Path) -> tuple[bool, str]:
    try:
        path.mkdir(parents=True, exist_ok=True)
        probe = path / ".rw-probe"
        probe.write_text("ok", encoding="utf-8")
        probe.unlink(missing_ok=True)
        return True, str(path)
    except Exception as exc:  # noqa: BLE001
        return False, str(exc)


def print_table(rows: list[dict]) -> None:
    headers = ["check", "status", "details"]
    widths = {h: len(h) for h in headers}
    for row in rows:
        for h in headers:
            widths[h] = max(widths[h], len(str(row.get(h, ""))))
    sep = " | "
    print(sep.join(h.ljust(widths[h]) for h in headers))
    print("-+-".join("-" * widths[h] for h in headers))
    for row in rows:
        print(sep.join(str(row.get(h, "")).ljust(widths[h]) for h in headers))


def main() -> int:
    parser = argparse.ArgumentParser(description="Preflight checks for Roo model introspection pipeline")
    parser.add_argument("--format", choices=["table", "json"], default="table")
    parser.add_argument("--verbose", action="store_true")
    args = parser.parse_args()

    root = project_root()
    config_dir = root / ".roo/config"

    checks: list[dict] = []

    def add(name: str, ok: bool, details: str) -> None:
        checks.append({"check": name, "status": "OK" if ok else "FAIL", "details": details})

    add("project_root", root.exists(), str(root))

    pipeline_cfg_path = config_dir / "pipeline.yaml"
    llm_cfg_path = config_dir / "llm.yaml"
    paths_cfg_path = config_dir / "paths.yaml"

    add("config.pipeline", pipeline_cfg_path.exists(), str(pipeline_cfg_path))
    add("config.llm", llm_cfg_path.exists(), str(llm_cfg_path))
    add("config.paths", paths_cfg_path.exists(), str(paths_cfg_path))

    if not (pipeline_cfg_path.exists() and llm_cfg_path.exists() and paths_cfg_path.exists()):
        if args.format == "json":
            print(json.dumps({"ok": False, "checks": checks}, ensure_ascii=False, indent=2))
        else:
            print_table(checks)
        return 1

    try:
        pipeline = load_config(pipeline_cfg_path)
        paths = load_config(paths_cfg_path)
    except RuntimeError as exc:
        add("config.parse", False, str(exc))
        if args.format == "json":
            print(json.dumps({"ok": False, "checks": checks}, ensure_ascii=False, indent=2))
        else:
            print_table(checks)
        return 1

    php_bin = str(pipeline.get("php_bin", "php8"))
    try:
        proc = subprocess.run([php_bin, "-v"], capture_output=True, text=True, check=False)
        detail = (proc.stdout or proc.stderr).splitlines()[0] if (proc.stdout or proc.stderr) else php_bin
        add("php", proc.returncode == 0, detail)
    except FileNotFoundError:
        add("php", False, f"binary not found in PATH: {php_bin}")

    tools_dir = as_abs(root, str(pipeline.get("tools_bin_dir", "./tools/bin")))
    for name in TOOLS_REQUIRED:
        p = tools_dir / name
        add(f"tool.{name}", p.is_file(), str(p))

    output_dirs: list[Path] = []
    runs_dir = as_abs(root, str(pipeline.get("runs_dir", "./.roo/runs")))
    output_dirs.append(runs_dir)

    registry = paths.get("registry", {}) if isinstance(paths.get("registry"), dict) else {}
    for key in ["entities", "relations", "inheritance", "discriminators", "issues"]:
        value = registry.get(key)
        if isinstance(value, str):
            output_dirs.append(as_abs(root, value).parent)

    snapshots_dir = paths.get("snapshots", {}).get("dir") if isinstance(paths.get("snapshots"), dict) else None
    if isinstance(snapshots_dir, str):
        output_dirs.append(as_abs(root, snapshots_dir))

    llm_dir = paths.get("llm", {}).get("results_dir") if isinstance(paths.get("llm"), dict) else None
    if isinstance(llm_dir, str):
        output_dirs.append(as_abs(root, llm_dir))

    reports_dir = paths.get("reports", {}).get("dir") if isinstance(paths.get("reports"), dict) else None
    if isinstance(reports_dir, str):
        output_dirs.append(as_abs(root, reports_dir))

    seen = set()
    for d in output_dirs:
        key = str(d)
        if key in seen:
            continue
        seen.add(key)
        ok, details = check_rw_dir(d)
        add(f"rw.{d.name}", ok, details)

    ok = all(item["status"] == "OK" for item in checks)
    result = {"ok": ok, "checks": checks}

    if args.format == "json":
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print_table(checks)

    if args.verbose:
        print(f"project_root={root}", file=sys.stderr)

    return 0 if ok else 1


if __name__ == "__main__":
    raise SystemExit(main())

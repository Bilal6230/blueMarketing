#!/usr/bin/env python3
"""Scan the WHOLE repository for ledger writes, including dynamic DB builders.

Static source INDEX, not semantic verification; ignores vendor, tests, storage
and generated cache. Never reads a financial database or private SQL.
"""
from __future__ import annotations

import argparse
from collections import Counter
from pathlib import Path
import json
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
SCOPE = ("app", "routes", "database", "resources/views", "Modules", "modules")
PATTERNS = {
    "direct_model": re.compile(
        r"\b(?:Ledger|CustomerLedger|DraftLedger|LabourLedger)::"
        r"(?:create|firstOrCreate|updateOrCreate|insert|upsert|where|query|find)\b"
        r"|\bnew\s+(?:Ledger|CustomerLedger|DraftLedger|LabourLedger)\s*\(",
        re.I,
    ),
    "literal_ledger_table": re.compile(
        r"(?:DB|\\DB)::(?:table|from)\s*\(\s*['\"]"
        r"(?:ledgers|customer_ledger|draft_ledgers|labour_ledgers)['\"]",
        re.I,
    ),
    "dynamic_table_builder": re.compile(
        r"(?:DB|\\DB)::table\s*\(\s*\$"
        r"|(?:DB|\\DB)::connection\s*\("
        r"|->table\s*\(\s*\$",
        re.I,
    ),
    "raw_statement": re.compile(
        r"(?:DB|\\DB)::(?:statement|unprepared|insert|update|delete)\s*\(",
        re.I,
    ),
    "explicit_finance_table": re.compile(
        r"['\"](?:ledgers|customer_ledger|draft_ledgers|labour_ledgers|"
        r"finance_posting_lines|finance_posting_batches|finance_posting_source_claims)['\"]",
        re.I,
    ),
}
V2_WRITE = re.compile(
    r"\b(?:finance_posting_lines|finance_posting_batches|finance_posting_source_claims)\b"
)
EXCLUDED = {".git", "vendor", "node_modules", "tests", "storage", "bootstrap/cache"}


def source_files() -> list[Path]:
    found = []
    for base in SCOPE:
        folder = ROOT / base
        if not folder.is_dir():
            continue
        for p in folder.rglob("*"):
            if p.suffix not in {".php", ".sql"} or not p.is_file():
                continue
            if any(part in EXCLUDED for part in p.relative_to(ROOT).parts):
                continue
            found.append(p)
    return sorted(set(found))


def audit() -> dict:
    found = []
    paths = source_files()
    for p in paths:
        rel = p.relative_to(ROOT).as_posix()
        method = "(scope)"
        for line_no, line in enumerate(p.read_text(encoding="utf-8", errors="replace").splitlines(), 1):
            match = re.search(r"\bfunction\s+([A-Za-z_]\w*)\s*\(", line)
            if match:
                method = match.group(1)
            kinds = [kind for kind, regex in PATTERNS.items() if regex.search(line)]
            if not kinds:
                continue
            found.append({
                "path": rel,
                "line": line_no,
                "method_hint": method,
                "kinds": kinds,
                "snippet": line.strip()[:200],
            })
    return {
        "kind": "syntactic-index-NOT-full-ownership-certification",
        "files_scanned": len(paths),
        "files_with_hits": len({x["path"] for x in found}),
        "hit_count": len(found),
        "kind_counts": dict(sorted(Counter(k for x in found for k in x["kinds"]).items())),
        "entries": found,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--out", type=Path, help="Optional repository-free JSON artifact path")
    parser.add_argument("--print-limit", type=int, default=25)
    args = parser.parse_args()
    result = audit()
    summary = {k: v for k, v in result.items() if k != "entries"}
    print(json.dumps(summary, indent=2))
    if args.out:
        out = args.out.resolve()
        if out == ROOT or ROOT in out.parents:
            raise ValueError("Audit artifacts belong outside Git working tree")
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(json.dumps(result, indent=2) + "\n", encoding="utf-8")
    for entry in result["entries"][:max(0, args.print_limit)]:
        print(f"{entry['path']}:{entry['line']} {entry['kinds']} {entry['snippet']}")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (OSError, ValueError) as e:
        print("AUDIT BLOCKED: " + str(e), file=sys.stderr)
        sys.exit(2)

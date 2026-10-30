#!/usr/bin/env python3
"""Generate NO-PII 57-table V2 staging fixture for disposable MySQL CI only."""
from __future__ import annotations

import argparse
import hashlib
import json
import pathlib
import sys

sys.path.insert(0, str(pathlib.Path(__file__).resolve().parents[1]))
from legacy_import_plan import ordered_tables
from v2_import_plan import verified_schema

SHA = "a" * 64
HEADER = "-- PRIVATE legacy source data. DO NOT commit. Not an executable migration.\n"


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--out", type=pathlib.Path, required=True)
    parser.add_argument("--invalid-fk", action="store_true")
    parser.add_argument("--windows-newlines", action="store_true", help="Simulate Python text-mode CRLF output")
    a = parser.parse_args()
    target = a.out.resolve()
    if target.exists() and any(target.iterdir()):
        raise RuntimeError("Fixture output already contains files")
    target.mkdir(parents=True, exist_ok=True)

    cols, original, extra, _ = verified_schema()
    deps = {n: set() for n in cols}
    for child, meta in original.items():
        for foreign in meta.get("foreign", []):
            deps[child].add(foreign["references"])
    for child, fields in extra.items():
        deps[child].update(p for p in fields.values() if p != "banks")
    order = ordered_tables(set(cols), deps)
    if len(order) != 57:
        raise RuntimeError("Expected 57 source entities")

    statements = {
        "projects": [
            "INSERT INTO `projects` (`id`, `project`, `address`, `is_active`, `create_by`, `created_at`, `updated_at`) "
            "VALUES (99999, 'CI; only', 'synthetic; address', 1, 1, '2020-02-03 04:05:06', '2020-04-05 06:07:08')"
        ],
        "legacy_migration_history": [
            "INSERT INTO `legacy_migration_history` (`id`, `migration`, `batch`) "
            "VALUES (42, 'synthetic_old_migration', 3)"
        ],
    }
    if a.invalid_fk:
        statements["sites"] = [
            "INSERT INTO `sites` (`id`, `project_id`, `subhead_accounting_id`, `site_name`) "
            "VALUES (999, 99999, 7777777, 'invalid fk rollback fixture')"
        ]

    metadata = []
    actual_names = ["legacy_migration_history" if n == "migrations" else n for n in order]
    for ix, name in enumerate(actual_names, 1):
        queries = statements.get(name, [])
        body = "\n".join(q + ";" for q in queries) + "\n"
        # Write bytes deliberately so CI on Linux can reproduce Windows-only
        # CRLF transformation without changing the canonical SHA manifest.
        raw = (HEADER + body).encode("utf-8")
        if a.windows_newlines:
            raw = raw.replace(b"\n", b"\r\n")
        (target / f"{ix:02d}_{name}.sql").write_bytes(raw)
        metadata.append({
            "source_table": "migrations" if name == "legacy_migration_history" else name,
            "target_table": name,
            "rows": len(queries),
            "insert_statements": len(queries),
            "content_sha256": hashlib.sha256(body.encode()).hexdigest(),
        })
    plan = {
        "source_sha256": SHA,
        "source_table_count": 57,
        "source_row_count": sum(x["rows"] for x in metadata),
        "old_fk_count": 40,
        "new_fk_candidates_applied": 46,
        "import_order": actual_names,
        "tables": metadata,
    }
    (target / "manifest.json").write_text(json.dumps(plan, indent=2) + "\n", encoding="utf-8")

    # Aggregate-only expected values for the optional read-only verifier.
    expected = {}
    for table, source_columns in cols.items():
        name = "legacy_migration_history" if table == "migrations" else table
        count = len(statements.get(name, []))
        has_id = "id" in source_columns
        synthetic_id = 99999 if table == "projects" else 42 if table == "migrations" else None
        dates = {}
        for column in ("created_at", "updated_at", "deleted_at"):
            if column not in source_columns:
                continue
            date_value = (("2020-02-03 04:05:06" if column == "created_at"
                           else "2020-04-05 06:07:08" if column == "updated_at" else None)
                          if table == "projects" else None)
            dates[column] = {
                "non_null": 1 if date_value else 0,
                "min": date_value,
                "max": date_value,
            }
        expected[table] = {
            "rows": count,
            "id_non_null": count if has_id else None,
            "id_min": synthetic_id if count and has_id else None,
            "id_max": synthetic_id if count and has_id else None,
            "timestamps": dates,
            "numeric_totals": {},
        }
    (target / "fake_source_fingerprints.json").write_text(
        json.dumps({
            "kind": "CI-only synthetic financial fingerprints",
            "source_sha256": SHA,
            "source_rows": plan["source_row_count"],
            "table_count": 57,
            "tables": expected,
        }, indent=2) + "\n", encoding="utf-8"
    )

    print(json.dumps({
        "fixture": "invalid-fk" if a.invalid_fk else "valid",
        "tables": len(metadata),
        "rows": plan["source_row_count"],
        "sha": SHA,
    }))


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""Produce a deterministic, FK-ordered, lossless V2 data-only import staging plan.

Never connects to or modifies a database. Requires explicit checksum and an
empty, OUT-OF-REPOSITORY directory to emit sensitive SQL files. The old
`migrations` rows are archived to V2 `legacy_migration_history` instead of
overwriting the new framework's own migration history.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import pathlib
import re
import sys

from legacy_import_plan import inspect, ordered_tables

ROOT = pathlib.Path(__file__).resolve().parents[2]
SCHEMA = ROOT / "database" / "v2_schema"
HISTORY = "legacy_migration_history"
SENSITIVE_COMMENT = "-- PRIVATE legacy source data. DO NOT commit. Not an executable migration.\n"
ORIGINAL_INSERT = re.compile(r"^INSERT INTO `([^\`]+)`\s*\(([^)]*)\) VALUES", re.S)


def verified_schema() -> tuple[dict[str, dict], dict[str, dict], str]:
    parts = {}
    hashes = set()
    for number in range(1, 5):
        p = SCHEMA / f"legacy_columns_{number:02d}.json"
        j = json.loads(p.read_text(encoding="utf-8"))
        hashes.add(j["source_sha256"])
        for x in j["tables"]:
            if x["table"] in parts:
                raise ValueError("Duplicate schema table: " + x["table"])
            parts[x["table"]] = dict(x["columns"])
    old = json.loads((SCHEMA / "legacy_constraints.json").read_text(encoding="utf-8"))
    newer = json.loads((SCHEMA / "v2_verified_extra_fks.json").read_text(encoding="utf-8"))
    hashes.update([old["source_sha256"], newer["source_sha256"]])
    if len(hashes) != 1 or len(parts) != 57 or old["foreign_keys"] != 40:
        raise ValueError("Source schema evidence mismatch (57 tables, 40 FKs required)")
    return parts, old["table_constraints"], newer["foreign_keys"], hashes.pop()


def stage_plan(source: pathlib.Path, expected_sha256: str, expected_rows: int | None = None) -> tuple[dict, dict[str, list[str]]]:
    manifest, chunks = inspect(source)
    cols, old_fks, new_fks, fingerprint = verified_schema()
    if manifest["source_sha256"] != fingerprint or manifest["source_sha256"] != expected_sha256.lower():
        raise ValueError("V2 source checksum mismatch. Refusing any import staging.")
    if manifest["table_count"] != 57 or set(cols) != set(chunks):
        raise ValueError("V2 source table inventory not exactly 57 tables")
    if expected_rows is not None and manifest["total_insert_rows"] != expected_rows:
        raise ValueError("V2 source row-count mismatch")

    dependencies = {name: set() for name in cols}
    edges = 0
    for table, metadata in old_fks.items():
        for foreign in metadata.get("foreign", []):
            dependencies[table].add(foreign["references"])
            edges += 1
    if edges != 40:
        raise ValueError(f"Expected 40 original FKs; got {edges}")
    added = 0
    for child, fields in new_fks.items():
        if child not in cols:
            raise ValueError("Additional FK child absent: " + child)
        for column, parent in fields.items():
            if column not in cols[child]:
                raise ValueError("Additional FK column absent: " + child + "." + column)
            if parent != "banks" and parent not in cols:
                raise ValueError("Additional FK parent absent: " + parent)
            dependencies[child].add(parent)
            added += 1
    if added != 46:
        raise ValueError("V2 FK registry changed unexpectedly; reverify historical source")

    # Banks table is separately seeded with stable 1-27 IDs before any inserts.
    dependencies = {name: parents - {"banks"} for name, parents in dependencies.items()}
    order = ordered_tables(set(cols), dependencies)
    if len(order) != 57:
        raise ValueError("V2 source dependency graph incomplete")

    staged = {}
    output = []
    for name in order:
        target = HISTORY if name == "migrations" else name
        statements = []
        for source_sql in chunks[name]:
            match = ORIGINAL_INSERT.match(source_sql)
            if match is None or match.group(1) != name:
                raise ValueError("Unexpected INSERT syntax for table " + name)
            declared_cols = [c.strip().strip("`") for c in match.group(2).split(",")]
            if len(declared_cols) != len(set(declared_cols)) or set(declared_cols) - set(cols[name]):
                raise ValueError("Unrecognized historical column for " + name)
            # Exact original source timestamps/amounts/IDs remain byte-for-byte.
            if target == HISTORY:
                statements.append(source_sql.replace("INSERT INTO `migrations`",
                                                     "INSERT INTO `legacy_migration_history`", 1))
            else:
                statements.append(source_sql)
        staged[target] = statements
        output.append({
            "source_table": name,
            "target_table": target,
            "rows": next(x["rows"] for x in manifest["tables"] if x["name"] == name),
            "insert_statements": len(statements),
            "content_sha256": hashlib.sha256(("\n".join(statements) + "\n").encode()).hexdigest(),
            "historical_id_policy": "preserve exactly",
            "historical_timestamps_policy": "preserve exact INSERT values",
        })

    plan = {
        "source_sha256": manifest["source_sha256"],
        "source_table_count": 57,
        "source_row_count": manifest["total_insert_rows"],
        "old_fk_count": 40,
        "new_fk_candidates_applied": 46,
        "requires_v2_banks_seeded": list(range(1, 28)),
        "framework_migrations_imported": False,
        "legacy_migration_history_rows": next(x["rows"] for x in output if x["source_table"] == "migrations"),
        "import_order": [x["target_table"] for x in output],
        "tables": output,
        "conditions": [
            "schema is created with isolated V2 migrations in order",
            "the 27 original bank lookup IDs are seeded first",
            "no FK checks disabled; no other SQL sessions write during import",
            "old type_id, reference and JV source ambiguities are NOT backfilled or corrected",
            "site.project_head_subhead_id is derived from the existing legacy site account FK",
            "import is not executed by this program; requires independent MySQL 8.4 rehearsal",
        ],
    }
    return plan, staged


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=pathlib.Path, required=True)
    parser.add_argument("--expected-sha256", type=str, required=True)
    parser.add_argument("--expected-rows", type=int)
    parser.add_argument("--out-dir", type=pathlib.Path)
    args = parser.parse_args()

    plan, files = stage_plan(args.source, args.expected_sha256, args.expected_rows)
    if args.out_dir is not None:
        directory = args.out_dir.resolve()
        if directory == ROOT or ROOT in directory.parents:
            raise ValueError("Sensitive SQL staging must never go inside the Git repository")
        if directory.exists() and any(directory.iterdir()):
            raise ValueError("Refusing to overwrite nonempty staging directory")
        directory.mkdir(parents=True, exist_ok=True)
        # Distinct files in strict dependency order. NO DDL or FK disabling.
        for idx, table in enumerate(plan["import_order"], start=1):
            sql = "\n".join(files[table]) + "\n"
            # Keep source line endings byte-for-byte independent of the
            # Windows host's text-mode newline translation. SHA256 is
            # computed on exactly this canonical text, not host CRLF output.
            with (directory / f"{idx:02d}_{table}.sql").open(
                "w", encoding="utf-8", newline="\n"
            ) as staged:
                staged.write(SENSITIVE_COMMENT + sql)
        (directory / "manifest.json").write_text(
            json.dumps(plan, indent=2) + "\n", encoding="utf-8"
        )
        (directory / "IMPORT_NOT_EXECUTED.txt").write_text(
            "DATA STAGING ONLY. No database was changed. Do not import to old/live databases.\n"
            "After isolated MySQL 8.4 V2 schema creation and 27-ID bank seed, "
            "review manifest and apply only in one separately approved session.\n"
            "For the sites table derive project_head_subhead_id from legacy "
            "subhead_accounting_id after verifying same project; this planner "
            "does not perform or fabricate derived updates.\n", encoding="utf-8"
        )
    print(json.dumps({
        "source_sha256": plan["source_sha256"],
        "tables": plan["source_table_count"],
        "rows": plan["source_row_count"],
        "legacy_history_rows": plan["legacy_migration_history_rows"],
        "old_fk_count": plan["old_fk_count"],
        "extra_fk_candidates": plan["new_fk_candidates_applied"],
        "order": plan["import_order"],
        "output_written": args.out_dir is not None,
    }, indent=2))
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (ValueError, OSError, UnicodeError) as exc:
        print("BLOCKED: " + str(exc), file=sys.stderr)
        sys.exit(2)

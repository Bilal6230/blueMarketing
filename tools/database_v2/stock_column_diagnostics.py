#!/usr/bin/env python3
"""PRIVATE, source-only column SHA evidence for 3 historic stock mismatches.

No raw values or identifiers are printed or stored. Full source SHA gate
prevents substituted dumps. Original source SQL and imported database untouched.
"""
from __future__ import annotations
import argparse
import json
import pathlib
import sys

from full_row_digests import SOURCE_SHA, canon, row_hash, table_hash
from legacy_import_plan import inspect
from rehearse_legacy_in_sqlite import source_rows
from v2_import_plan import verified_schema

TABLES = ("stock_entries", "stock_movements", "stock_parties")
ROOT = pathlib.Path(__file__).resolve().parents[2]


def inspect_columns(source: pathlib.Path) -> dict:
    metadata, _, _, sha = verified_schema()
    manifest, chunks = inspect(source)
    if sha != SOURCE_SHA or manifest["source_sha256"] != SOURCE_SHA or (
        manifest["table_count"] != 57 or manifest["total_insert_rows"] != 120165
    ):
        raise ValueError("SOURCE_SHA_OR_TABLE_COUNT_MISMATCH")
    result = {}
    for table in TABLES:
        columns = metadata[table]
        rows = source_rows(chunks[table], table)
        reference = next(x["rows"] for x in manifest["tables"] if x["name"] == table)
        if len(rows) != reference or any(set(row) != set(columns) for row in rows):
            raise ValueError("SOURCE_STOCK_ROW_COLUMNS_MISMATCH")
        digests = {}
        for column, definition in columns.items():
            hashes = [row_hash([canon(record[column], definition)]) for record in rows]
            digests[column] = table_hash(hashes)
        result[table] = {"rows": len(rows), "columns": digests}
    return {
        "kind": "v2-private-stock-column-parity-v1",
        "source_sha256": SOURCE_SHA,
        "scope": list(TABLES),
        "tables": result
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=pathlib.Path, required=True)
    parser.add_argument("--out", type=pathlib.Path, required=True)
    args = parser.parse_args()
    out = args.out.resolve()
    if out == ROOT or ROOT in out.parents or out.exists():
        raise ValueError("PRIVATE_EVIDENCE_OUTPUT_MUST_BE_NEW_AND_OUTSIDE_GIT")
    stats = inspect_columns(args.source)
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(stats, indent=2) + "\n", encoding="utf8")
    print("STOCK_COLUMN_SOURCE_EVIDENCE_READY: 3 tables, source SHA verified")
    for table in TABLES:
        print("SOURCE_TABLE:", table, "rows=", stats["tables"][table]["rows"],
              "columns=", len(stats["tables"][table]["columns"]))
    print("Do not upload the private digest file to GitHub.")


if __name__ == "__main__":
    try:
        main()
    except (OSError, ValueError, UnicodeError) as exc:
        print("STOCK_SOURCE_DIAGNOSTIC_BLOCKED:", str(exc), file=sys.stderr)
        sys.exit(2)

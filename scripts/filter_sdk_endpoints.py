#!/usr/bin/env python3
"""Filter the live OpenAPI spec to SDK-tagged endpoints only.

This keeps only operations tagged with "SDK", includes the transitive schema
closure for those operations, and rewrites the operation tags to just "SDK" so
the generator emits a single public API surface.
"""

from __future__ import annotations

import json
import sys
from copy import deepcopy
from pathlib import Path
from typing import Any


def collect_schema_refs(obj: Any, schemas: set[str], visited: set[int]) -> None:
    if obj is None:
        return

    obj_id = id(obj)
    if obj_id in visited:
        return
    visited.add(obj_id)

    if isinstance(obj, dict):
        ref = obj.get("$ref")
        if isinstance(ref, str) and ref.startswith("#/components/schemas/"):
            schemas.add(ref.split("/")[-1])

        for value in obj.values():
            collect_schema_refs(value, schemas, visited)
    elif isinstance(obj, list):
        for item in obj:
            collect_schema_refs(item, schemas, visited)


def collect_all_related_schemas(spec: dict[str, Any], initial_schemas: set[str]) -> set[str]:
    all_schemas = spec.get("components", {}).get("schemas", {})
    collected = set(initial_schemas)
    queue = list(initial_schemas)

    while queue:
        schema_name = queue.pop()
        schema = all_schemas.get(schema_name)
        if schema is None:
            continue

        refs: set[str] = set()
        collect_schema_refs(schema, refs, set())
        for ref in refs:
            if ref not in collected:
                collected.add(ref)
                queue.append(ref)

    return collected


def filter_sdk_endpoints(spec: dict[str, Any]) -> dict[str, Any]:
    filtered_paths: dict[str, Any] = {}
    used_schemas: set[str] = set()

    for path, methods in spec.get("paths", {}).items():
        filtered_methods: dict[str, Any] = {}

        for method, operation in methods.items():
            if not isinstance(operation, dict):
                continue

            tags = operation.get("tags", [])
            if "SDK" not in tags:
                continue

            normalized_operation = deepcopy(operation)
            normalized_operation["tags"] = ["SDK"]
            filtered_methods[method] = normalized_operation
            collect_schema_refs(normalized_operation, used_schemas, set())

        if filtered_methods:
            filtered_paths[path] = filtered_methods

    all_related_schemas = collect_all_related_schemas(spec, used_schemas)
    components = spec.get("components", {})

    filtered_spec: dict[str, Any] = {
        "openapi": spec["openapi"],
        "info": spec["info"],
        "servers": spec.get("servers", []),
        "paths": filtered_paths,
        "components": {
            "schemas": {},
            "securitySchemes": components.get("securitySchemes", {}),
        },
        "tags": [
            {
                "name": "SDK",
                "description": "Public SDK operations",
            }
        ],
    }

    if "security" in spec:
        filtered_spec["security"] = spec["security"]

    all_schemas = components.get("schemas", {})
    for schema_name in sorted(all_related_schemas):
        if schema_name in all_schemas:
            filtered_spec["components"]["schemas"][schema_name] = all_schemas[schema_name]

    return filtered_spec


def main() -> None:
    if len(sys.argv) != 3:
        print("Usage: python scripts/filter_sdk_endpoints.py <input.json> <output.json>")
        sys.exit(1)

    input_path = Path(sys.argv[1])
    output_path = Path(sys.argv[2])

    if not input_path.exists():
        print(f"Error: input file not found: {input_path}")
        sys.exit(1)

    with input_path.open() as handle:
        spec = json.load(handle)

    filtered_spec = filter_sdk_endpoints(spec)

    with output_path.open("w") as handle:
        json.dump(filtered_spec, handle, indent=2)
        handle.write("\n")

    endpoint_count = sum(len(methods) for methods in filtered_spec["paths"].values())
    schema_count = len(filtered_spec["components"]["schemas"])
    print(f"Filtered {endpoint_count} SDK endpoints")
    print(f"Included {schema_count} related schemas")
    print(f"Wrote {output_path}")


if __name__ == "__main__":
    main()


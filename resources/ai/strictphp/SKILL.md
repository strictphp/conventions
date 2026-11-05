---
name: strictphp
description: Apply shared StrictPHP principles when creating, changing, or reviewing PHP code.
---

# StrictPHP implementation

Read the target project's applicable `AGENTS.md` and `CONTRIBUTING.md`. Apply their short shared rules, then use the guidance below for the implementation. Framework and package skills add their specific conventions.

## Types and transport

Declare input and output types. Use typed DTOs for data passed across application boundaries. Preserve the distinction between application data and protocol data: HTTP payloads, serialized Options, and parsed external responses may use arrays and external field names. Convert at the boundary instead of passing wire-format arrays throughout the application.

## Collaborators and APIs

Inject collaborators through constructors. Data objects still receive caller-supplied values; do not convert their constructors into service injection points. Let a framework skill define supported lifecycle injection when a framework constructs the object.

Before using a library or framework API, inspect the version installed in the target project. Check signatures, namespaces, named arguments, and construction requirements against that source.

## Validation

Use the project's existing Composer scripts and test runner. Apply Rector transformations before ECS formatting, then run PHPStan and relevant tests. Review transformations for behavior changes. Preserve the observable behavior of existing code during convention adoption.

Keep formatting rules in ECS and code transformations in Rector. Do not repeat formatter settings in agent instructions. Report failed or blocked checks rather than treating configuration installation as successful adoption.

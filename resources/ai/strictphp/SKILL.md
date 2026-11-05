---
name: strictphp
description: Apply shared StrictPHP principles when creating, changing, or reviewing PHP code.
---

# StrictPHP principles

Use explicit types for inputs and outputs. Use constructor injection for collaborators and typed DTOs at application boundaries. Use arrays at protocol boundaries, such as HTTP payloads and serialized data.

Before using a library or framework API, inspect the version installed in the target project. Do not rely on memory or documentation for another version.

Use the project's ECS and Rector configuration for formatting and code transformations. Do not add formatting rules to agent instructions.

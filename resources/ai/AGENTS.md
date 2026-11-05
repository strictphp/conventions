# StrictPHP conventions

## MUST USE

- Use `$strictphp` for PHP implementation details and the relevant package skills for framework-specific work.
- Use explicit input and output types, constructor injection for collaborators, and typed DTOs at application boundaries.
- Verify APIs against the installed package version.
- Run Rector first, then ECS, then PHPStan, with tests last.

## DON'T USE

- Do not use unstructured arrays in place of typed application data; arrays are allowed at protocol boundaries.
- Do not duplicate formatting rules in agent instructions; use the project's ECS and Rector configuration.

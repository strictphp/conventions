# StrictPHP Conventions development

Read the local [StrictPHP skill](.agents/skills/strictphp/SKILL.md) and the [shared agent instructions](resources/ai/AGENTS.md).

Keep reusable rules in `src/` and shared tool configuration in `extension-*` files. Store distributable AI instructions and skills in `resources/ai/`. The `.agents/skills/strictphp` symlink exposes the source skill during development. Do not run consumer sync over this symlink.

Add regression tests for changed rule or sync behavior. Before commit or release, run `composer lint:upgrade:check` (Rector), `composer lint:check` (ECS), then `composer analyse` (PHPStan), followed by `composer test`.

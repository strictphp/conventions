# AI agent setup

[Back to README](../README.md)

StrictPHP Conventions provides the shared `strictphp` skill. It also discovers skills registered by installed Composer packages and skills registered by the root project.

## Register project skills

Add skills to the root project's `composer.json` under `extra.strictphp/conventions.skills`. The skill key is its general name. The value is a path relative to the package or project root that contains `SKILL.md`.

```json
{
    "extra": {
        "strictphp/conventions": {
            "skills": {
                "project-api": "resources/ai/project-api"
            },
            "instructions": ["resources/ai/AGENTS.md"]
        }
    }
}
```

Each `SKILL.md` must have single-line YAML `name` and `description` values. Its `name` must match the skill key. Skill paths must stay within the package or project. Skill files cannot be symlinks. The sync command reads `vendor/composer/installed.json` and the root project Composer metadata. It does not execute PHP from packages.

The sync command installs the bundled StrictPHP skill, discovered package skills, and root project skills. It collects package rules in the managed root `AGENTS.md` block, preserving text outside that block. It does not generate a skill catalog or copy skill descriptions into `AGENTS.md`. The harness discovers installed skills from their `SKILL.md` metadata. It writes skills to `.agents/skills/` and tracks generated files in `.agents/.strictphp-manifest.json`. The Composer `skills` map registers files for installation; it is not a skill routing table for `AGENTS.md`. The manifest records version 1, package ownership, and each generated file's hash. Edited generated files and duplicate skill names stop sync; skills cannot override one another.

Keep local instructions outside the generated block.

## Distribute package instructions

Packages and the root project can register Markdown files in `extra["strictphp/conventions"].instructions`. Use a list of paths relative to the package or project root. A package can provide instructions without skills. These files must exist inside that root and cannot use symlinks or sync markers. The generated root `AGENTS.md` cannot also be an input; use a separate source file such as `resources/ai/AGENTS.md`.

Start each instruction file with its own package title as a level-one heading, followed by MUST USE and DON'T USE sections. Keep only the most important package rules there. A short directive may name `$strictphp`, `$larastrict`, or relevant package skills for implementation work. Put detailed conventions, exceptions, examples, and task workflows in the skills and their focused references. Do not add a skill catalog or repeat descriptions in `AGENTS.md`.

StrictPHP provides the shared baseline from `resources/ai/AGENTS.md`. Sync reads the registered files and adds their content to the generated root `AGENTS.md` block without adding headings of its own. Package-authored titles and their MUST USE and DON'T USE sections are preserved unchanged. Packages are sorted by name; files within a package keep their registered order. The root project's registrations use the same format. Keep project-specific overrides outside the generated block or in directory instructions.

Changes and removed registrations are detected by `--check`. The next sync updates or removes that package's section. Links in instruction fragments resolve from the consumer's root `AGENTS.md`, so use paths such as `.agents/skills/project-api/SKILL.md`.

StrictPHP's own root `AGENTS.md` covers repository development and links directly to its source instructions and local skill symlink. It is separate from the distributable baseline.

## Composer scripts

Register the sync command after install and update. Composer runs scripts from the root project.

```json
{
    "scripts": {
        "strictphp:sync-ai": "@php -r \"if (is_file('vendor/strictphp/conventions/bin/strictphp-sync-ai')) { require 'vendor/strictphp/conventions/bin/strictphp-sync-ai'; }\"",
        "strictphp:check-ai": "@php vendor/strictphp/conventions/bin/strictphp-sync-ai --check",
        "post-install-cmd": ["@strictphp:sync-ai"],
        "post-update-cmd": ["@strictphp:sync-ai"]
    }
}
```

The install and update hooks skip sync when the binary is absent, such as an install with `--no-dev`. The explicit check command fails when the binary is absent. In CI, run `composer strictphp:check-ai` after installing development dependencies.

Commit the generated `AGENTS.md` block, `.agents/skills/` files, and `.agents/.strictphp-manifest.json`. To preview or check a local package checkout:

```bash
php /path/to/conventions/bin/strictphp-sync-ai --project /path/to/application
php /path/to/conventions/bin/strictphp-sync-ai --project /path/to/application --check
```

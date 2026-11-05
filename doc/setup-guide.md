# Set up StrictPHP Conventions

[Back to README](../README.md)

Use this guide to install the tools and AI instructions in a PHP project. For a
Laravel project, use `larastrict/conventions`, which extends StrictPHP.

## Approval before setup

For an AI-assisted installation, read this guide and inspect the project without
changing files or running setup commands. Summarize the proposed dependencies,
configuration, Composer scripts, and generated AI files. Ask for permission to
perform the installation and wait for an explicit answer before proceeding.
This approval covers setup and validation. Ask separately before an upgrade
session that changes existing application code.

## 1. Inspect the project

Read the project's applicable `AGENTS.md` and `CONTRIBUTING.md` files. Check its
PHP version, Composer dependencies, framework version, source paths, test runner,
and existing quality tools. Preserve unrelated changes and project-specific
configuration. Merge existing scripts and hooks instead of replacing them.

The current StrictPHP package requires PHP 8.3 or later. Select package versions
that support the project's PHP and framework versions. Use installed package
source and documentation to verify APIs and configuration. This guide describes
versions that include `bin/strictphp-sync-ai`; older versions may lack that command.
Report a dependency conflict or a missing feature instead of disabling checks.

## 2. Install the development dependencies

For a PHP project:

```bash
composer config allow-plugins.phpstan/extension-installer true
composer require --dev strictphp/conventions
```

For a Laravel project:

```bash
composer config allow-plugins.phpstan/extension-installer true
composer require --dev larastrict/conventions
```

Larastrict requires StrictPHP, so both packages are installed for Laravel. Keep
other required development tools and test integrations. Resolve overlapping
version constraints without removing tools the project still needs. Do not use
`--ignore-platform-reqs` to bypass PHP or extension requirements.

Use `phpstan/extension-installer` for PHPStan extension discovery. Do not also
include the same extension manually. Preserve other Composer plugin settings.

## 3. Configure Rector and ECS

Use the project's actual source and test paths. Include only paths that exist.
Exclude installed dependencies, generated files, and caches. Preserve deliberate
project rules and exclusions when merging configuration.

For a plain PHP project, create or update `rector.php`:

```php
<?php declare(strict_types=1);

use Rector\Config\RectorConfig;
use StrictPhp\Conventions\ExtensionFiles;

return RectorConfig::configure()
    ->withRootFiles()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSets([ExtensionFiles::Rector]);
```

Create or update `ecs.php` with the same paths:

```php
<?php declare(strict_types=1);

use StrictPhp\Conventions\ExtensionFiles;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withRootFiles()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSets([ExtensionFiles::Ecs]);
```

For Laravel, use `LaraStrict\Conventions\ExtensionFiles` in both files. Its
`Rector` and `Ecs` sets already include the shared StrictPHP sets. Set paths to the
application's `app`, `config`, `database`, `routes`, and `tests` directories, plus
any other maintained PHP directories. Include existing bootstrap PHP files.
Use the installed Larastrict [tool setup guide](https://github.com/larastrict/conventions/blob/main/docs/setup.md)
for framework-specific configuration.

## 4. Configure PHPStan

Create or merge `phpstan.neon`. Use the same maintained code boundaries as the
other tools. A plain PHP package can start with:

```neon
parameters:
    level: max
    paths:
        - src
        - tests
        - ecs.php
        - rector.php
```

For Laravel, configure the application paths and preserve its Larastan bootstrap,
reflection, and test integrations. Container rules need the application and its
providers to be loaded. Include existing application view directories when using
Larastrict's Blade checks. Do not analyse compiled views or vendor templates.
See the installed Larastrict documentation for rule configuration.

Use the shared rule level for a new setup. Keep intentional settings in an
existing setup and report differences. Do not lower the level, disable rules, or
add a baseline just to make installation checks pass.

## 5. Add the Composer scripts

Use these names and execution order. Merge them into the root `composer.json`:

```json
{
    "scripts": {
        "qa": [
            "@lint",
            "@analyse",
            "@test"
        ],
        "lint": [
            "@lint:upgrade",
            "@lint:fix"
        ],
        "lint:upgrade": "vendor/bin/rector process",
        "lint:upgrade:check": "vendor/bin/rector process --dry-run",
        "lint:fix": "vendor/bin/ecs --fix",
        "lint:check": "vendor/bin/ecs",
        "analyse": "vendor/bin/phpstan",
        "lint:stan": "@analyse",
        "test": "vendor/bin/phpunit"
    }
}
```

Preserve an existing test command, such as a Laravel or Pest test runner, instead
of replacing it with PHPUnit. Keep required environment variables and test setup.
If script names already exist, merge their required work into this workflow and
update dependent aliases without losing existing steps.

`composer qa` runs Rector, then ECS, then PHPStan, with tests last. `lint` applies
changes; it is not a read-only check. `analyse` is the standard PHPStan command;
`lint:stan` is an alias for users of that name.

## 6. Install the AI instructions and skills

Merge these scripts and hooks into the root `composer.json`. Keep existing hook
commands in their current order and append the sync hook once:

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

These examples use Composer's default `vendor` directory. Adapt both script paths
if the project uses `config.vendor-dir`. The sync engine discovers registrations
from that configured directory.

Run the initial sync and check:

```bash
composer strictphp:sync-ai
composer strictphp:check-ai
```

The guarded install and update hooks skip sync when development dependencies are
absent. The explicit check must fail if the command is missing. Verify that the
initial sync creates the expected files; a skipped hook is not a complete setup.

Sync installs the StrictPHP skill, registered package skills, and package
instructions. Laravel projects also receive the Larastrict skills. Keep local
project rules outside the managed root `AGENTS.md` block or in directory
instructions. Do not replace consumer copies with symlinks.

Keep the generated `AGENTS.md` block, `.agents/skills/` files, and
`.agents/.strictphp-manifest.json` in version control. Preserve local changes if a
sync conflict occurs. Move local guidance out of generated files before retrying.
The [AI setup reference](../docs/ai-agents.md) explains how packages and the root
project can register additional instructions and skills.

## 7. Validate the setup

Run the read-only tool checks and the existing test runner listed below. Resolve
setup errors and rerun affected checks in the order Rector, ECS, PHPStan, and
tests. During installation, report existing code violations without applying
code transformations or fixing application code.

If an existing project has more violations than the requested adoption scope,
report them separately from setup errors. Do not report QA as passing while
checks fail. Report missing services, extensions, credentials, or other blocked
checks with their causes.

For CI or a final check without code transformations, run these commands in order:

```bash
composer lint:upgrade:check
composer lint:check
composer analyse
composer test
composer strictphp:check-ai
```

To check committed AI files in CI, install development dependencies with
`composer install --no-scripts` before the AI check. Otherwise an automatic sync
can update stale files before the check reads them. Run any application setup
commands that the skipped Composer hooks normally provide and that validation
requires.

Report the installed packages, configuration and script changes, generated AI
files, passing checks, and any failed or blocked checks. Installing configuration
and resolving every violation in an existing application are separate outcomes.

## Optional upgrade session

After installation and its validation report, ask: "Would you like to start an
upgrade session using `$strictphp` to bring the existing code into compliance?"
For a Laravel project, use `$larastrict`, which extends `$strictphp`.
Wait for the user's answer. Installation approval alone does not authorize this
upgrade session.

If approved, agree on the scope and use the main skill with the relevant package
skills. Preserve application behavior. Run Rector first, then ECS, then PHPStan,
with tests last. Review transformations and move renamed files to their PSR-4
paths before PHPStan runs. Use `composer qa` for the agreed scope when appropriate;
it applies code changes. Report changes and any remaining failed or blocked checks.

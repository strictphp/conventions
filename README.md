# StrictPHP Conventions

![.github/banner.webp](.github/banner.webp)

[![Latest Stable Version](https://poser.pugx.org/strictphp/conventions/v)](https://packagist.org/packages/strictphp/conventions)
[![Total Downloads](https://poser.pugx.org/strictphp/conventions/downloads)](https://packagist.org/packages/strictphp/conventions)
[![License](https://poser.pugx.org/strictphp/conventions/license)](https://packagist.org/packages/strictphp/conventions)
[![PHP Version Require](https://poser.pugx.org/strictphp/conventions/require/php)](https://packagist.org/packages/strictphp/conventions)
![badge](https://img.shields.io/endpoint?url=https://gist.githubusercontent.com/pionl/0b0340bf7e806524a632347d0b9219ea/raw/coverage.json)

Welcome to StrictPHP Conventions, a Composer package designed to standardize PHP code across various our projects. This
package is an assembly of tools and configurations to enhance code quality, maintainability, and adherence to best
practices. It's crafted with care for developers seeking consistency and excellence in their projects, and we encourage
its adoption. Pull Requests are welcome!

## Features Included

This package bundles a selection of powerful tools and configurations to support your development workflow:

- **PHPStan Configuration and Custom Rules**: Integrates [PHPStan](https://phpstan.org), with pre-defined configurations
  and rules tailored for our projects.
- **Easy Coding Standard Configuration**: Utilizes [Easy Coding Standard](https://github.com/symplify/coding-standard)
  for enforcing consistent coding styles and practices.
- **PHPUnit Support**: Ensures compatibility with [PHPUnit](https://phpunit.de) to facilitate comprehensive
  testing.
- **RectorPHP Configuration**: Provides configurations for [RectorPHP](https://getrector.org) for better code quality.
- **Extended PHPStan Packages**: Includes additional packages to augment PHPStan's capabilities. For more details, see
  the included `composer.json` file.

For Laravel use [LaraStrict conventions](https://github.com/larastrict/conventions).

See [AI agent setup](docs/ai-agents.md) to install the shared PHP skill and discover skills from Composer packages and the root project.

## Prerequisites

Before installing, please ensure your environment meets the following requirements:

- PHP version 8.3 or higher for the current package.
- Composer
- Enabled [Extension installer for PHPStan](https://github.com/phpstan/extension-installer) - it is installed by our
  package to allow extension discovery.

**Only the latest major version of this package is maintained.**

| Version | PHPUnit  | PHP  |
|---------|----------|------|
| 2.x     | 10–13    | 8.3+ |
| 1.x     | 10/11    | 8.1+ |
| 0.x     | 9        | 8.1+ |

## Installation

Copy this prompt into your coding agent:

```text
Set up StrictPHP Conventions in this project.

Fetch and read:
https://raw.githubusercontent.com/strictphp/conventions/main/doc/setup-guide.md

Read the project's existing agent and contribution instructions first.
Inspect its PHP version, framework, dependencies, and tool configuration.

Before running setup commands or changing files, summarize the proposed
installation, including dependency, configuration, script, and AI file changes.
Ask for permission to proceed and wait for the user's answer.
After approval, follow the guide.

Set up the development dependencies, Rector, ECS, PHPStan, Composer scripts,
and AI instruction sync. For Laravel projects, use larastrict/conventions,
which extends strictphp/conventions.

Merge existing configuration and Composer hooks. Preserve project-specific
settings and the existing test runner.

Use the recommended Composer script names and execution order from the guide:
Rector → ECS → PHPStan → tests.

Validate setup with the guide's read-only checks and the existing test runner.
Resolve setup problems and report existing code violations or blocked checks.
Do not disable rules or generate baselines just to make the checks pass.

Summarize the changes and validation results. Then ask whether the user wants
an upgrade session using $strictphp (or $larastrict for Laravel) to bring
existing code into compliance.
Wait for approval before applying code transformations or fixing existing code.
If approved, agree on the scope and run Rector → ECS → PHPStan → tests.
```

For manual installation, follow the [setup guide](doc/setup-guide.md).
The guide covers dependencies, tool configuration, Composer scripts, AI sync,
and validation. See the [AI setup reference](docs/ai-agents.md) to distribute
additional package instructions and skills.

The standard `composer qa` workflow runs Rector, ECS, PHPStan, then tests.
It applies code transformations and formatting. The setup guide also lists
read-only commands for CI.

## Review or upgrade existing code

After AI sync, the agent can discover the installed skills from their descriptions
and select them for normal implementation work. You do not need to name a skill
for each task. Use an explicit skill prompt when you want a convention review or
an upgrade session for existing code.

To adopt the conventions in an existing codebase:

```text
Use $strictphp to bring this project's existing code into compliance
with the installed conventions.

Inspect the codebase and apply the relevant skills and references.
Preserve application behavior and project-specific instructions.

Run Rector first, then ECS, then PHPStan, with tests last.
Fix reported violations without disabling rules or adding baselines.
Report changes and any remaining failures.
```

For a review without code changes:

```text
Use $strictphp to review src/Orders and its tests against the installed conventions.
Report findings with file references and suggested fixes. Do not change files.
```

To apply the fixes, request an upgrade session for that scope.

For Laravel applications, use `$larastrict`, which extends `$strictphp`.

## Integrate your own package

A Composer package can distribute its own AI rules and skills through StrictPHP.
Use the same registration for plain PHP and Laravel packages:

```json
{
    "extra": {
        "strictphp/conventions": {
            "instructions": ["resources/ai/AGENTS.md"],
            "skills": {
                "my-package": "resources/ai/my-package"
            }
        }
    }
}
```

Keep essential MUST USE and DON'T USE rules under your package's own heading in
`resources/ai/AGENTS.md`. Put implementation details in
`resources/ai/my-package/SKILL.md`, with frontmatter `name: my-package` and a
`description` that explains when the agent should use it. Include these files in
the published package. Both registrations are optional.

In the consuming project, install the package and run `composer strictphp:sync-ai`
after configuring the [setup guide](doc/setup-guide.md). Sync collects its rules
into `AGENTS.md` and installs its skills in `.agents/skills/`. Commit the generated
files and run `composer strictphp:check-ai` in CI. Composer runs sync hooks from
the root project, so a package's own hooks do not configure its consumers.

See [AI agent setup](docs/ai-agents.md) for registration requirements and examples.

## Acknowledgement

This project owes its existence to the generous support of several other impactful projects:

- **[Canvastera](https://canvastera.com)** - Empowering users to craft multimedia posters and share them worldwide. (
  EDU/Hobby)
- **[Azzurro Travel Agency](https://azzurro.cz)** - Specializing in holidays in Italy.
- **[Redtag Studio](https://redtag.studio)** - Crafting digital products for your enjoyment.

Explore more of our open-source initiatives:

- **[Larastrict](https://larastrict.com)** - Enhancing the Laravel Framework with a suite of convenient tools and
  packages.
- **[StrictPHP](https://strictphp.com)** - Enabling strictness in PHP projects through a curated set of packages and
  conventions, fostering the development of robust production-grade applications.
- **[WrkFlow](https://wrk-flow.com)** - Streamlining development workflows with a comprehensive set of tools designed to
  boost efficiency.

## License

Open-source software licensed under the [MIT License](LICENSE.md). Feel free to use and modify it according to your
needs.

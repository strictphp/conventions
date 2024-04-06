# StrictPHP Conventions

![.github/banner.webp](.github/banner.webp)

[![Latest Stable Version](https://poser.pugx.org/strictphp/conventions/v)](https://packagist.org/packages/strictphp/conventions)
[![Total Downloads](https://poser.pugx.org/strictphp/conventions/downloads)](https://packagist.org/packages/strictphp/conventions)
[![License](https://poser.pugx.org/strictphp/conventions/license)](https://packagist.org/packages/strictphp/conventions)
[![PHP Version Require](https://poser.pugx.org/strictphp/conventions/require/php)](https://packagist.org/packages/strictphp/conventions)

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
- **PHPUnit 9 Support**: Ensures compatibility with [PHPUnit 9](https://phpunit.de) to facilitate comprehensive testing.
- **RectorPHP Configuration**: Provides configurations for [RectorPHP](https://getrector.org) for better code quality.
- **Extended PHPStan Packages**: Includes additional packages to augment PHPStan's capabilities. For more details, see
  the included `composer.json` file.

## Prerequisites

Before installing, please ensure your environment meets the following requirements:

- PHP version 8.1 or higher.
- Composer

## Installation

To incorporate StrictPHP Conventions into your project, simply run the following command in your terminal:

```bash
composer require strictphp/conventions --dev
```

This will install the package as a development dependency.

## Getting Started

After installation, you can extend the provided configurations to suit your project's specific needs.

### Integrating PHPStan

For PHPStan, create a `phpstan.neon` file in your project's root directory with the following content to integrate our
conventions:

```neon
includes:
    - vendor/strictphp/conventions/phpstan.neon
```

PHPStan will then automatically include your local configurations for ECS and Rector in its analysis, enhancing the code
quality checks.

### Setting Up Easy Coding Standard

To use the Easy Coding Standard, create an `ecs.php` file at your project's root with this setup or update your
configuration:

```php
<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withRootFiles()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // This is required to include the StrictPHP Conventions
    ->withSets([__DIR__ . '/vendor/strictphp/conventions/config/ecs.php']);
```

### Configuring RectorPHP

For integrating RectorPHP, add a `rector.php` file in your project's root with the following configuration or update
your configuration :

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withRootFiles()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    // This is required to include the StrictPHP Conventions
    ->withSets([
        __DIR__ . '/vendor/strictphp/conventions/config/rector.php'
    ]);
```

This setup ensures that your project is aligned with StrictPHP Conventions, leveraging the combined power of these tools
to maintain high-quality code standards.

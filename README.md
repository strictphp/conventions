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
- Enabled [Extension installer for PHPStan](https://github.com/phpstan/extension-installer) - it is installed by our
  package to allow extension discovery.

## Installation

To incorporate StrictPHP Conventions into your project, simply run the following command in your terminal:

```bash
composer require strictphp/conventions --dev
```

This will install the package as a development dependency. You will be asked to confirm the installation of the plugin:

> Do you trust "phpstan/extension-installer" to execute code and wish to enable it now? (yes/no) [y]:

Type `y` to use all the extensions provided by the package.

## Getting Started

After installation, you can extend the provided configurations to suit your project's specific needs.

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
    ->withSets([\StrictPhp\Conventions\ExtensionFiles::Ecs]);
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
    ->withSets([\StrictPhp\Conventions\ExtensionFiles::Rector]);
```

### Integrating PHPStan

It is required you to use phpstan extension installer to install the required extensions. This is recommended setup:

```neon
parameters:
  level: max
  paths:
      - src
      - ecs.php
      - rector.php
      - extension-ecs.php
      - extension-rector.php
```

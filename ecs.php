<?php

declare(strict_types=1);

use StrictPhp\Conventions\ExtensionFiles;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withRootFiles()
    ->withSets([ExtensionFiles::Ecs]);

<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load Rector's parser before PHPUnit coverage loads a second copy.
require_once __DIR__ . '/../vendor/rector/rector/preload.php';

<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use StrictPhp\Conventions\Rector\Renaming\CarbonCreateToCreateStrictRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->rule(CarbonCreateToCreateStrictRector::class);
};

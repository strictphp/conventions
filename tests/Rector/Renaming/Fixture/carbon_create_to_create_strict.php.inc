<?php

namespace StrictPhp\ConventionsTests\Rector\Renaming\Fixture;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

class SomeClass
{
    public function run()
    {
        // Testing named arguments
        $range = [
            'from' => Carbon::create(year: 2023, day: 20),
            'to' => Carbon::create(year: 2023, day: 27),
        ];

        // Testing standard positional arguments
        $standard = Carbon::create(2023, 1, 20);
        
        // Testing CarbonImmutable
        $immutable = CarbonImmutable::create(year: 2023, day: 20);
    }
}

?>
-----
<?php

namespace StrictPhp\ConventionsTests\Rector\Renaming\Fixture;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

class SomeClass
{
    public function run()
    {
        // Testing named arguments
        $range = [
            'from' => Carbon::createStrict(year: 2023, day: 20),
            'to' => Carbon::createStrict(year: 2023, day: 27),
        ];

        // Testing standard positional arguments
        $standard = Carbon::createStrict(2023, 1, 20);
        
        // Testing CarbonImmutable
        $immutable = CarbonImmutable::createStrict(year: 2023, day: 20);
    }
}

?>

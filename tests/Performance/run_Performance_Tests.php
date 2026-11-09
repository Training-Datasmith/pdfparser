<?php

declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';

use Performance_Tests\Exception\Performance_Fail_Exception;
use Performance_Tests\Test\Document_Dictionary_Cache_Test;

$tests = [
    new Document_Dictionary_Cache_Test(),
];

foreach ($tests as $test) {
    $test->init();

    $startTime = microtime(true);
    $test->run();
    $endTime = microtime(true);

    $time = $endTime - $startTime;

    if ($test->get_max_estimated_time() <= $time) {
        $msg = sprintf(
            'Performance failed on test "%s". Time taken was %.2f seconds, expected less than %d seconds.',
            get_class($test),
            $time,
            $test->get_max_estimated_time()
        );

        throw new Performance_Fail_Exception($msg);
    }
}

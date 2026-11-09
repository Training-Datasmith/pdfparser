<?php

declare(strict_types=1);

namespace Performance_Tests;

abstract class Abstract_Performance_Test
{
    /**
     * Initializes the test (eg, fetches the files etc).
     */
    abstract public function init(): void;

    /**
     * Executes the test.
     */
    abstract public function run(): void;

    /**
     * Returns the time over which the test is considered a fail.
     */
    abstract public function get_max_estimated_time(): int;
}

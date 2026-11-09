#!/usr/bin/env php
<?php

declare(strict_types=1);

if (!ini_get('date.timezone')) {
    ini_set('date.timezone', 'UTC');
}

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';
require $root.'/dev-tools/vendor/autoload.php';

if (extension_loaded('mbstring')) {
    fwrite(STDERR, "phpunit-polyfill: refuse to run while ext-mbstring is loaded.\n");
    exit(1);
}

if (!function_exists('mb_strlen')) {
    fwrite(STDERR, "phpunit-polyfill: mb_strlen is missing; symfony/polyfill-mbstring did not bootstrap.\n");
    exit(1);
}

$requiredExtensions = ['dom', 'filter', 'json', 'libxml', 'mbstring', 'tokenizer', 'xmlwriter'];
$unavailableExtensions = array_filter(
    $requiredExtensions,
    static function (string $extension): bool {
        if ($extension === 'mbstring' && function_exists('mb_strlen')) {
            return false;
        }

        return !extension_loaded($extension);
    }
);

if ([] !== $unavailableExtensions) {
    fwrite(
        STDERR,
        sprintf(
            'PHPUnit requires the "%s" extensions, but the "%s" %s not available.' . PHP_EOL,
            implode('", "', $requiredExtensions),
            implode('", "', $unavailableExtensions),
            count($unavailableExtensions) === 1 ? 'extension is' : 'extensions are'
        )
    );
    exit(1);
}

unset($requiredExtensions, $unavailableExtensions);

exit((new PHPUnit\TextUI\Application())->run($_SERVER['argv']));

<?php

declare(strict_types=1);

/**
 * Fail if cluster packages are not installed at the expected index SHA and root alias.
 */

$expected = array(
    'symfony/polyfill-mbstring' => array(
        'alias' => '1.33.0',
        'reference' => '2cecae254e4ffc2c20ac379c5cd23d91c82109df',
    ),
);

$installedPhp = __DIR__.'/../vendor/composer/installed.php';
if (!is_readable($installedPhp)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
    exit(1);
}

/** @var array{versions: array<string, array{reference?: string, aliases?: list<string>}>} $installed */
$installed = require $installedPhp;

/**
 * @param array<string, array{alias: string, reference: string}> $map
 * @param list<string>          $messages
 */
function check_cluster_pins(array $map, array $versions, &$messages = null)
{
    $failed = false;
    $localMessages = array();

    foreach ($map as $name => $want) {
        if (!isset($versions[$name])) {
            $line = sprintf("check-cluster-pins: %s is not installed.\n", $name);
            $localMessages[] = $line;
            fwrite(STDERR, $line);
            $failed = true;
            continue;
        }

        $pkg = $versions[$name];
        $aliases = isset($pkg['aliases']) && is_array($pkg['aliases']) ? $pkg['aliases'] : array();
        if (!in_array($want['alias'], $aliases, true)) {
            $line = sprintf(
                "check-cluster-pins: %s alias mismatch (expected %s in aliases %s).\n",
                $name,
                $want['alias'],
                json_encode($aliases)
            );
            $localMessages[] = $line;
            fwrite(STDERR, $line);
            $failed = true;
        }

        $reference = isset($pkg['reference']) ? $pkg['reference'] : null;
        if (!is_string($reference) || $reference === '') {
            $line = sprintf("check-cluster-pins: %s has no reference.\n", $name);
            $localMessages[] = $line;
            fwrite(STDERR, $line);
            $failed = true;
            continue;
        }

        if (!hash_equals($want['reference'], $reference)) {
            $line = sprintf(
                "check-cluster-pins: %s reference mismatch (expected %s, got %s).\n",
                $name,
                $want['reference'],
                $reference
            );
            $localMessages[] = $line;
            fwrite(STDERR, $line);
            $failed = true;
        }
    }

    if ($messages !== null) {
        $messages = $localMessages;
    }

    return !$failed;
}

$versions = isset($installed['versions']) && is_array($installed['versions'])
    ? $installed['versions']
    : array();

$wrongShaDemo = $expected;
$wrongShaDemo['symfony/polyfill-mbstring']['reference'] = '0000000000000000000000000000000000000000';

$wrongMessages = array();
$wrongFailed = !check_cluster_pins($wrongShaDemo, $versions, $wrongMessages);

if (!$wrongFailed) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo should have failed but did not.\n");
    exit(1);
}

$wrongOutput = implode('', $wrongMessages);
if (strpos($wrongOutput, 'reference mismatch') === false) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo output missing reference mismatch.\n");
    fwrite(STDERR, $wrongOutput);
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo rejected bad reference as expected.\n");

if (!check_cluster_pins($expected, $versions)) {
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);

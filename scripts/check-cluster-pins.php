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
$installedJson = __DIR__.'/../vendor/composer/installed.json';

if (!is_readable($installedPhp)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.php not found; run composer update first.\n");
    exit(1);
}

if (!is_readable($installedJson)) {
    fwrite(STDERR, "check-cluster-pins: vendor/composer/installed.json not found; run composer update first.\n");
    exit(1);
}

/** @var array{versions: array<string, array{reference?: string, aliases?: list<string>}>} $installed */
$installed = require $installedPhp;

$rawJson = file_get_contents($installedJson);
if ($rawJson === false) {
    fwrite(STDERR, "check-cluster-pins: could not read vendor/composer/installed.json.\n");
    exit(1);
}

/** @var mixed $json */
$json = json_decode($rawJson, true);
if (!is_array($json)) {
    fwrite(STDERR, "check-cluster-pins: invalid JSON in vendor/composer/installed.json.\n");
    exit(1);
}

$jsonPackages = array();
if (isset($json['packages']) && is_array($json['packages'])) {
    foreach ($json['packages'] as $pkg) {
        if (is_array($pkg) && isset($pkg['name']) && is_string($pkg['name'])) {
            $jsonPackages[$pkg['name']] = $pkg;
        }
    }
}
if (isset($json['packages-dev']) && is_array($json['packages-dev'])) {
    foreach ($json['packages-dev'] as $pkg) {
        if (is_array($pkg) && isset($pkg['name']) && is_string($pkg['name'])) {
            $jsonPackages[$pkg['name']] = $pkg;
        }
    }
}

/**
 * @param array<string, array{alias: string, reference: string}> $map
 * @param array<string, array{reference?: string, aliases?: list<string>}> $versions
 * @param list<string> $messages
 */
function check_installed_php_pins(array $map, array $versions, &$messages = null)
{
    $failed = false;
    $localMessages = array();

    foreach ($map as $name => $want) {
        if (!isset($versions[$name])) {
            $line = sprintf("check-cluster-pins: %s is not installed (installed.php).\n", $name);
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
            $line = sprintf("check-cluster-pins: %s has no reference in installed.php.\n", $name);
            $localMessages[] = $line;
            fwrite(STDERR, $line);
            $failed = true;
            continue;
        }

        if (!hash_equals($want['reference'], $reference)) {
            $line = sprintf(
                "check-cluster-pins: %s installed.php reference mismatch (expected %s, got %s).\n",
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

/**
 * @param array<string, array{alias: string, reference: string}> $map
 * @param array<string, array<string, mixed>>                  $packages
 * @param list<string>                                         $messages
 */
function check_installed_json_pins(array $map, array $packages, &$messages = null)
{
    $failed = false;
    $localMessages = array();

    foreach ($map as $name => $want) {
        if (!isset($packages[$name])) {
            $line = sprintf("check-cluster-pins: %s is not installed (installed.json).\n", $name);
            $localMessages[] = $line;
            fwrite(STDERR, $line);
            $failed = true;
            continue;
        }

        $pkg = $packages[$name];
        $source = isset($pkg['source']) && is_array($pkg['source']) ? $pkg['source'] : array();
        $reference = isset($source['reference']) ? $source['reference'] : null;
        if (!is_string($reference) || $reference === '') {
            $line = sprintf("check-cluster-pins: %s has no source.reference in installed.json.\n", $name);
            $localMessages[] = $line;
            fwrite(STDERR, $line);
            $failed = true;
            continue;
        }

        if (!hash_equals($want['reference'], $reference)) {
            $line = sprintf(
                "check-cluster-pins: %s installed.json source.reference mismatch (expected %s, got %s).\n",
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

$wrongPhp = $expected;
$wrongPhp['symfony/polyfill-mbstring']['reference'] = '0000000000000000000000000000000000000000';
$wrongPhpMessages = array();
$wrongPhpFailed = !check_installed_php_pins($wrongPhp, $versions, $wrongPhpMessages);
if (!$wrongPhpFailed) {
    fwrite(STDERR, "check-cluster-pins: installed.php wrong-SHA demo should have failed but did not.\n");
    exit(1);
}
$wrongPhpOutput = implode('', $wrongPhpMessages);
if (strpos($wrongPhpOutput, 'installed.php reference mismatch') === false) {
    fwrite(STDERR, "check-cluster-pins: installed.php wrong-SHA demo output missing reference mismatch.\n");
    fwrite(STDERR, $wrongPhpOutput);
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: installed.php wrong-SHA demo rejected bad reference as expected.\n");

$wrongJson = $expected;
$wrongJson['symfony/polyfill-mbstring']['reference'] = '1111111111111111111111111111111111111111';
$wrongJsonMessages = array();
$wrongJsonFailed = !check_installed_json_pins($wrongJson, $jsonPackages, $wrongJsonMessages);
if (!$wrongJsonFailed) {
    fwrite(STDERR, "check-cluster-pins: installed.json wrong-SHA demo should have failed but did not.\n");
    exit(1);
}
$wrongJsonOutput = implode('', $wrongJsonMessages);
if (strpos($wrongJsonOutput, 'installed.json source.reference mismatch') === false) {
    fwrite(STDERR, "check-cluster-pins: installed.json wrong-SHA demo output missing source.reference mismatch.\n");
    fwrite(STDERR, $wrongJsonOutput);
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: installed.json wrong-SHA demo rejected bad source.reference as expected.\n");

if (!check_installed_php_pins($expected, $versions)) {
    exit(1);
}

if (!check_installed_json_pins($expected, $jsonPackages)) {
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);

<?php

declare(strict_types=1);

/** Validate all inputs before producing updated files. */
function prepareReleaseContents(string $manifest, array $modules, string $version, string $date): array
{
    if (!preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version)) {
        throw new RuntimeException('Version must use X.Y.Z format without leading zeros.');
    }
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
        throw new RuntimeException('Date must be a valid YYYY-MM-DD date.');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $xml = simplexml_load_string($manifest, SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false || $xml->getName() !== 'extension'
            || count($xml->version) !== 1 || count($xml->release_date) !== 1
            || trim((string) $xml->extension_code) !== 'br-peripherals') {
            throw new RuntimeException('Expected one version and release_date in the peripherals extension manifest.');
        }
        $currentVersion = trim((string) $xml->version);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $expectedModules = ['br-peripherals', 'br-costcenter-bridge-for-br-peripherals', 'br-peripherals-bridge-for-teemip-ip-mgmt'];
    $providedModules = array_keys($modules);
    sort($expectedModules);
    sort($providedModules);
    if ($providedModules !== $expectedModules) {
        throw new RuntimeException('Expected the main module and both bridge modules.');
    }
    foreach ($modules as $name => $module) {
        $pattern = "~'" . preg_quote($name, '~') . "/([^']+)'~";
        if (preg_match_all($pattern, $module, $matches) !== 1 || $matches[1][0] !== $currentVersion) {
            throw new RuntimeException('Expected one matching registration version for ' . $name . '.');
        }
    }
    // Internal dependency versions follow this release; external minimum versions do not.
    $internalPattern = "~'(" . implode('|', array_map(
        static fn (string $name): string => preg_quote($name, '~'),
        $expectedModules
    )) . ")/([^'|]+)(?='|\\|\\|)~";
    foreach ($modules as $module) {
        preg_match_all($internalPattern, $module, $references, PREG_SET_ORDER);
        foreach ($references as $reference) {
            if ($reference[2] !== $currentVersion) {
                throw new RuntimeException('Internal dependency version does not match: ' . $reference[1] . '.');
            }
        }
    }
    if (!version_compare($version, $currentVersion, '>')) {
        throw new RuntimeException('The release version must be newer than ' . $currentVersion . '.');
    }
    foreach (['version' => $version, 'release_date' => $date] as $element => $value) {
        $manifest = preg_replace_callback(
            '~<' . $element . '>.*?</' . $element . '>~s',
            static fn (): string => "<$element><![CDATA[$value]]></$element>",
            $manifest,
            -1,
            $count
        );
        if ($manifest === null || $count !== 1) {
            throw new RuntimeException('Expected exactly one editable ' . $element . ' element.');
        }
    }
    foreach ($modules as $name => $module) {
        $modules[$name] = preg_replace_callback(
            $internalPattern,
            static fn (array $match): string => "'{$match[1]}/$version",
            $module
        );
    }
    return [$manifest, $modules];
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        if ($argc < 2 || $argc > 3) {
            throw new RuntimeException('Usage: php scripts/prepare-release.php X.Y.Z [YYYY-MM-DD]');
        }
        $date = $argv[2] ?? (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
        $root = dirname(__DIR__);
        $moduleNames = ['br-peripherals', 'br-costcenter-bridge-for-br-peripherals', 'br-peripherals-bridge-for-teemip-ip-mgmt'];
        $paths = [$root . '/extension.xml'];
        foreach ($moduleNames as $name) {
            $paths[] = "$root/$name/module.$name.php";
        }
        $originals = [];
        foreach ($paths as $path) {
            $content = file_get_contents($path);
            if ($content === false || !is_writable($path)) {
                throw new RuntimeException('Cannot read and write ' . $path);
            }
            $originals[] = $content;
        }
        [$manifest, $modules] = prepareReleaseContents($originals[0], array_combine($moduleNames, array_slice($originals, 1)), $argv[1], $date);
        $updated = array_merge([$manifest], array_values($modules));
        try {
            foreach ($paths as $index => $path) {
                if (file_put_contents($path, $updated[$index], LOCK_EX) !== strlen($updated[$index])) {
                    throw new RuntimeException('Cannot write ' . $path);
                }
            }
        } catch (Throwable $error) {
            foreach ($paths as $index => $path) {
                if (file_put_contents($path, $originals[$index], LOCK_EX) !== strlen($originals[$index])) {
                    fwrite(STDERR, "Could not restore $path; inspect it manually.\n");
                }
            }
            throw $error;
        }
        echo "Prepared version {$argv[1]} with release date $date. Running tests...\n";
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/run.php'), $status);
        if ($status !== 0) {
            fwrite(STDERR, "Tests failed. Version edits are retained for inspection; do not publish yet.\n");
        }
        exit($status);
    } catch (Throwable $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/../scripts/prepare-release.php';

$manifest = file_get_contents(__DIR__ . '/../extension.xml');
$modules = [];
foreach (['br-peripherals', 'br-costcenter-bridge-for-br-peripherals', 'br-peripherals-bridge-for-teemip-ip-mgmt'] as $name) {
    $modules[$name] = file_get_contents(__DIR__ . '/../' . $name . '/module.' . $name . '.php');
}
$xml = simplexml_load_string($manifest);
$current = trim((string) $xml->version);
$parts = explode('.', $current);
$next = $parts[0] . '.' . $parts[1] . '.' . ((int) $parts[2] + 1);

$tests = [
    'prepare matching release metadata without changing other content' => static function () use ($manifest, $modules, $current, $next): void {
        [$updatedManifest, $updatedModules] = prepareReleaseContents($manifest, $modules, $next, '2028-02-29');
        $xml = simplexml_load_string($updatedManifest);
        foreach ($xml->modules->module as $entry) {
            if ((string) $entry->version !== $next) {
                throw new RuntimeException('Manifest module version was not updated.');
            }
        }
        $expectedModules = [];
        foreach ($modules as $name => $content) {
            foreach (array_keys($modules) as $internalName) {
                $content = str_replace("'$internalName/$current", "'$internalName/$next", $content);
            }
            $expectedModules[$name] = $content;
        }
        if ((string) $xml->version !== $next || (string) $xml->release_date !== '2028-02-29'
            || $updatedModules !== $expectedModules
            || preg_replace('~<(version|release_date)>.*?</\1>~s', '', $updatedManifest)
                !== preg_replace('~<(version|release_date)>.*?</\1>~s', '', $manifest)) {
            throw new RuntimeException('Unexpected release metadata or unrelated edits.');
        }
    },
];

$cases = [
    'reject missing module array' => [preg_replace('~<modules.*?</modules>~s', '', $manifest), $modules, $next, '2028-02-29'],
    'reject mismatched manifest module version' => [str_replace(
        '<id>br-peripherals</id><version><![CDATA[' . $current . ']]>',
        '<id>br-peripherals</id><version><![CDATA[0.0.0]]>',
        $manifest
    ), $modules, $next, '2028-02-29'],
    'reject duplicate manifest module' => [str_replace(
        '<id>br-costcenter-bridge-for-br-peripherals</id>',
        '<id>br-peripherals</id>',
        $manifest
    ), $modules, $next, '2028-02-29'],
    'reject missing bridge module' => [$manifest, array_slice($modules, 0, 2, true), $next, '2028-02-29'],
    'reject malformed version' => [$manifest, $modules, 'v3.3.0', '2028-02-29'],
    'reject version with leading zeros' => [$manifest, $modules, '03.3.0', '2028-02-29'],
    'reject unchanged version' => [$manifest, $modules, $current, '2028-02-29'],
    'reject older version' => [$manifest, $modules, '0.0.0', '2028-02-29'],
    'reject invalid calendar date' => [$manifest, $modules, $next, '2027-02-29'],
    'reject ambiguous module version' => [$manifest, array_replace($modules, ['br-peripherals' => $modules['br-peripherals'] . $modules['br-peripherals']]), $next, '2028-02-29'],
    'reject mismatched module version' => [$manifest, array_replace($modules, ['br-peripherals' => str_replace("'br-peripherals/$current'", "'br-peripherals/0.0.0'", $modules['br-peripherals'])]), $next, '2028-02-29'],
    'reject missing date element' => [preg_replace('~<release_date>.*?</release_date>~s', '', $manifest), $modules, $next, '2028-02-29'],
];
foreach ($modules as $name => $content) {
    $cases['reject mismatched version in ' . $name] = [
        $manifest, array_replace($modules, [$name => str_replace("'$name/$current'", "'$name/0.0.0'", $content)]), $next, '2028-02-29',
    ];
}
foreach (['br-costcenter-bridge-for-br-peripherals', 'br-peripherals-bridge-for-teemip-ip-mgmt'] as $bridge) {
    $cases['reject stale internal dependency in ' . $bridge] = [
        $manifest,
        array_replace($modules, [$bridge => str_replace("'br-peripherals/$current'", "'br-peripherals/0.0.0'", $modules[$bridge])]),
        $next,
        '2028-02-29',
    ];
}
foreach ($cases as $name => $arguments) {
    $tests[$name] = static function () use ($arguments): void {
        try {
            prepareReleaseContents(...$arguments);
        } catch (RuntimeException $error) {
            return;
        }
        throw new RuntimeException('Invalid release input was accepted.');
    };
}

return $tests;

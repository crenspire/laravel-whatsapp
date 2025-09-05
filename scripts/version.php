<?php

/**
 * Version Management Script
 * 
 * This script handles semantic versioning for the Laravel WhatsApp package.
 */

if ($argc < 2) {
    echo "Usage: php scripts/version.php [show|patch|minor|major]\n";
    exit(1);
}

$command = $argv[1];
$composerFile = __DIR__ . '/../composer.json';

if (!file_exists($composerFile)) {
    echo "Error: composer.json not found\n";
    exit(1);
}

$composer = json_decode(file_get_contents($composerFile), true);
$version = explode('.', $composer['version']);

switch ($command) {
    case 'show':
        echo $composer['version'] . "\n";
        break;
        
    case 'patch':
        $version[2]++;
        updateVersion($composer, $version, $composerFile);
        break;
        
    case 'minor':
        $version[1]++;
        $version[2] = 0;
        updateVersion($composer, $version, $composerFile);
        break;
        
    case 'major':
        $version[0]++;
        $version[1] = 0;
        $version[2] = 0;
        updateVersion($composer, $version, $composerFile);
        break;
        
    default:
        echo "Error: Unknown command '$command'\n";
        echo "Usage: php scripts/version.php [show|patch|minor|major]\n";
        exit(1);
}

function updateVersion($composer, $version, $composerFile) {
    $newVersion = implode('.', $version);
    $composer['version'] = $newVersion;
    
    // Update version in extra section if it exists
    if (isset($composer['extra']['ramsey/composer-version']['version'])) {
        $composer['extra']['ramsey/composer-version']['version'] = $newVersion;
    }
    
    $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    file_put_contents($composerFile, $json);
    
    echo "Version updated to: $newVersion\n";
}

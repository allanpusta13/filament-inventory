<?php

declare(strict_types=1);
// Simple .env parser for validation
$envFile = __DIR__.'/.env';
$envVars = [];

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = mb_trim($line);
        if (mb_strpos($line, '#') === 0 || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $envVars[mb_trim($key)] = mb_trim($value);
    }
}

// Validate required variables
$required = [
    'APP_NAME' => 'Larament',
    'APP_ENV' => 'local',
    'APP_KEY' => '', // Just check if it's set
    'APP_DEBUG' => 'true',
    'APP_URL' => 'http://localhost:8000',
];

$allValid = true;
foreach ($required as $key => $expectedValue) {
    if (! isset($envVars[$key])) {
        echo "❌ Missing: {$key}\n";
        $allValid = false;
    } elseif ($expectedValue !== '' && $envVars[$key] !== $expectedValue) {
        echo "❌ Incorrect: {$key} = {$envVars[$key]}, expected {$expectedValue}\n";
        $allValid = false;
    } else {
        echo "✅ {$key} = {$envVars[$key]}\n";
    }
}

if ($allValid) {
    echo "\n🎉 All environment variables are correctly configured!\n";
} else {
    echo "\n⚠️  Some environment variables need attention.\n";
}

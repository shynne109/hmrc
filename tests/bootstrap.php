<?php

error_reporting(E_ALL | E_STRICT);

// composer autoloader - find the correct path depending on installation context
$autoloaderPath = null;

// First check if this is a standalone installation (normal case)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    $autoloaderPath = __DIR__ . '/../vendor/autoload.php';
}
// Otherwise check if this is a composer-managed package (vendor/shynne109/hmrc case)
// Look for autoloader in ../../.. (vendor/vendor/autoload.php doesn't exist, so we go up to root project)
elseif (file_exists(__DIR__ . '/../../../../vendor/autoload.php')) {
    $autoloaderPath = __DIR__ . '/../../../../vendor/autoload.php';
}
// Fallback for other structures
elseif (file_exists(__DIR__ . '/../../../autoload.php')) {
    $autoloaderPath = __DIR__ . '/../../../autoload.php';
}

if (!$autoloaderPath) {
    die('Could not find Composer autoloader. Expected it at one of: ' . "\n" .
        __DIR__ . '/../vendor/autoload.php' . "\n" .
        __DIR__ . '/../../../../vendor/autoload.php' . "\n" .
        __DIR__ . '/../../../autoload.php' . "\n");
}

$autoloader = require $autoloaderPath;
$autoloader->add('GovTalk\\GiftAid\\',__DIR__);

// Explicitly require PAYE TestCase so names can resolve
if (file_exists(__DIR__ . '/GovTalk/PAYE/TestCase.php')) {
	require_once __DIR__ . '/GovTalk/PAYE/TestCase.php';
}

// Explicitly require GiftAid TestCase too (namespace differs)
if (file_exists(__DIR__ . '/GovTalk/GiftAid/TestCase.php')) {
	require_once __DIR__ . '/GovTalk/GiftAid/TestCase.php';
}

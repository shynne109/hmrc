<?php

error_reporting(E_ALL | E_STRICT);

// composer autoloader
$autoloader = require __DIR__.'/../vendor/autoload.php';
$autoloader->add('GovTalk\\GiftAid\\',__DIR__);

// Explicitly require PAYE TestCase so names can resolve
if (file_exists(__DIR__ . '/GovTalk/PAYE/TestCase.php')) {
	require_once __DIR__ . '/GovTalk/PAYE/TestCase.php';
}

// Explicitly require GiftAid TestCase too (namespace differs)
if (file_exists(__DIR__ . '/GovTalk/GiftAid/TestCase.php')) {
	require_once __DIR__ . '/GovTalk/GiftAid/TestCase.php';
}

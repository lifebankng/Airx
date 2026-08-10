<?php

require_once __DIR__ . '/rb.php';

// Ensure vendor autoload is present for Dotenv if not loaded yet
if (!class_exists('Dotenv\Dotenv')) {
	$autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
	if (file_exists($autoloadPath)) {
		require_once $autoloadPath;
	}
}

if (class_exists('Dotenv\Dotenv')) {
	$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 2));
	$dotenv->safeLoad();
}

$dbhost = $_ENV['DB_HOST'] ?? $_ENV['dbhost'] ?? 'localhost';
$dbuser = $_ENV['DB_USER'] ?? $_ENV['dbuser'] ?? 'lifebank_user';
$dbpass = $_ENV['DB_PASS'] ?? $_ENV['dbpass'] ?? '';
$dbname = $_ENV['DB_NAME'] ?? $_ENV['dbname'] ?? 'airx';

if (!R::testConnection()) {
	R::setup('mysql:host=' . $dbhost . ';dbname=' . $dbname, $dbuser, $dbpass);
}
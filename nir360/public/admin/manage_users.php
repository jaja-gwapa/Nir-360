<?php
// Run as if the request was for index.php with path /admin/manage_users (so routing matches)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$base = dirname(dirname($scriptName));
$_SERVER['SCRIPT_NAME'] = $base . '/index.php';
$path = rtrim($base, '/') . '/admin/manage_users';
$_SERVER['REQUEST_URI'] = $path . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
require dirname(__DIR__) . '/index.php';

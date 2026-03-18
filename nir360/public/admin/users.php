<?php
$_SERVER['REQUEST_URI'] = preg_replace('~/admin/users\.php$~', '/admin/users', (string)($_SERVER['REQUEST_URI'] ?? '/admin/users.php'));
require dirname(__DIR__) . '/index.php';

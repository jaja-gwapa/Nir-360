<?php
$_SERVER['REQUEST_URI'] = preg_replace('~/admin/manage-users\.php$~', '/admin/manage-users', (string)($_SERVER['REQUEST_URI'] ?? '/admin/manage-users.php'));
require dirname(__DIR__) . '/index.php';

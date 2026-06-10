<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

$mode = (string)($_GET['mode'] ?? 'all');
auth_logout($mode === 'admin');
auth_redirect('admin.php?page=master');

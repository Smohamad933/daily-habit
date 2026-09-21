<?php
require __DIR__ . '/common.php';
monitoring_logout();
header('Location: ' . monitor_url('login.php'));
exit;

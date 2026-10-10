<?php
require_once __DIR__ . '/includes/auth.php';
require_csrf_or_die();
do_logout();
header('Location: ' . base_url() . '/index.php');
exit;

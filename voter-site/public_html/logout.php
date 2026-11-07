<?php
require __DIR__ . '/inc/bootstrap.php';
start_session();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: index.php'); exit; }
check_csrf();
$u = current_user();
if ($u) log_activity((int)$u['id'], 'logout');
forget_cookie();
$_SESSION = [];
session_destroy();
setcookie('MCSESS', '', ['expires' => time() - 3600, 'path' => '/']);
header('Location: login.php');

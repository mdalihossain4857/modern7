<?php
require __DIR__ . '/inc/bootstrap.php';
if (!is_installed()) { header('Location: install.php'); exit; }
send_security_headers();
start_session();
if (current_user()) { header('Location: index.php'); exit; }

const MAX_FAILS = 8;      // ১৫ মিনিটে এতবার ভুল হলে সাময়িক ব্লক
$err = ''; $user = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    $ip = client_ip();
    $db = db();
    $db->exec('DELETE FROM login_attempts WHERE at < NOW() - INTERVAL 1 DAY');
    $st = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > NOW() - INTERVAL 15 MINUTE');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() >= MAX_FAILS) {
        $err = 'অনেকবার ভুল চেষ্টা হয়েছে। ১৫ মিনিট পরে আবার চেষ্টা করুন।';
    } else {
        $st = $db->prepare('SELECT id, pass_hash, active FROM users WHERE username = ?');
        $st->execute([$user]);
        $u = $st->fetch();
        if ($u && $u['active'] && password_verify($pass, $u['pass_hash'])) {
            if (password_needs_rehash($u['pass_hash'], PASSWORD_DEFAULT))
                $db->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')->execute([password_hash($pass, PASSWORD_DEFAULT), $u['id']]);
            $db->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
            do_login((int)$u['id'], !empty($_POST['remember']));
            log_activity((int)$u['id'], 'login');
            header('Location: index.php'); exit;
        }
        $db->prepare('INSERT INTO login_attempts (ip, username) VALUES (?,?)')->execute([$ip, mb_substr($user, 0, 50)]);
        $err = ($u && !$u['active']) ? 'এই অ্যাকাউন্টটি বন্ধ করা আছে।' : 'ইউজারনেম অথবা পাসওয়ার্ড সঠিক নয়।';
    }
}
$S = settings();
$logo = '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="5" y="7" width="38" height="25" rx="4" stroke="#fff" stroke-width="3"/><path d="M17 40h14M24 32v8" stroke="#fff" stroke-width="3" stroke-linecap="round"/><path d="M15 25V14.5l4.5 5.5L24 14.5 28.5 20 33 14.5V25" stroke="#7fc4ff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?><!DOCTYPE html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>লগইন — <?= h($S['shop_name']) ?></title><link rel="stylesheet" href="assets/app.css"></head>
<body class="login-locked">
<div id="loginGate" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
  <div class="mc-brand"><div class="mc-logo"><?= $logo ?></div><h2 class="mc-name"><span><?= h($S['shop_name']) ?></span></h2></div>
  <div class="mc-rgb"><form id="loginBox" method="post" autocomplete="on" class="<?= $err ? 'shake' : '' ?>">
    <h2 id="loginTitle">🔐 ইউজার লগইন</h2>
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <label for="loginUser">ইউজারনেম</label><input id="loginUser" name="username" type="text" value="<?= h($user) ?>" required autofocus autocomplete="username" autocapitalize="off" spellcheck="false">
    <label for="loginPass">পাসওয়ার্ড</label><input id="loginPass" name="password" type="password" required autocomplete="current-password">
    <label class="rem"><input type="checkbox" name="remember" value="1" checked> আমাকে মনে রাখুন (৩০ দিন)</label>
    <button id="loginBtn" type="submit">লগ ইন</button>
    <div id="loginErr" aria-live="polite"><?= h($err) ?></div>
  </form></div>
  <div class="mc-credit"><?= h($S['credit']) ?></div>
</div>
</body></html>

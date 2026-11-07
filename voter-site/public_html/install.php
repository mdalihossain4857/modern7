<?php
/* প্রথমবার সেটআপ: ডাটাবেস টেবিল তৈরি + অ্যাডমিন অ্যাকাউন্ট + config.php লেখা */
require __DIR__ . '/inc/bootstrap.php';
send_security_headers();
start_session();

$err = ''; $done = false; $manual = '';
$f = ['db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'admin_user' => '', 'admin_name' => ''];

if (is_installed()) {
    $already = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    foreach ($f as $k => $_) $f[$k] = trim((string)($_POST[$k] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $p1 = (string)($_POST['admin_pass'] ?? ''); $p2 = (string)($_POST['admin_pass2'] ?? '');
    if (!$f['db_name'] || !$f['db_user']) $err = 'ডাটাবেসের নাম ও ইউজার দিন।';
    elseif (!preg_match('/^[A-Za-z0-9_.@-]{3,50}$/', $f['admin_user'])) $err = 'অ্যাডমিন ইউজারনেম ৩-৫০ অক্ষরের হবে (ইংরেজি অক্ষর/সংখ্যা)।';
    elseif (strlen($p1) < 8) $err = 'পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের দিন।';
    elseif ($p1 !== $p2) $err = 'দুইটি পাসওয়ার্ড মিলছে না।';
    else {
        try {
            $pdo = new PDO('mysql:host=' . $f['db_host'] . ';dbname=' . $f['db_name'] . ';charset=utf8mb4', $f['db_user'], $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            foreach (array_filter(array_map('trim', explode(';', file_get_contents(__DIR__ . '/inc/schema.sql')))) as $sql) {
                $sql = trim(preg_replace('/^--.*$/m', '', $sql));
                if ($sql !== '') $pdo->exec($sql);
            }
            $pdo->prepare('INSERT INTO users (username, full_name, pass_hash, role) VALUES (?,?,?,\'admin\')')
                ->execute([$f['admin_user'], $f['admin_name'] ?: $f['admin_user'], password_hash($p1, PASSWORD_DEFAULT)]);
            $ins = $pdo->prepare('INSERT IGNORE INTO settings (k, v) VALUES (?,?)');
            foreach (DEFAULT_SETTINGS as $k => $v) $ins->execute([$k, $v]);

            $conf = "<?php\n// install.php দ্বারা তৈরি — এই ফাইল কাউকে দেখাবেন না\nreturn " . var_export([
                'db_host' => $f['db_host'], 'db_name' => $f['db_name'], 'db_user' => $f['db_user'], 'db_pass' => $dbPass,
            ], true) . ";\n";
            if (@file_put_contents(CONFIG_FILE, $conf) === false) $manual = $conf;
            $done = true;
        } catch (PDOException $e) {
            $err = 'ডাটাবেস সংযোগ/টেবিল তৈরিতে সমস্যা: ' . $e->getMessage();
        }
    }
}
?><!DOCTYPE html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>সাইট সেটআপ</title><link rel="stylesheet" href="assets/admin.css"></head><body>
<main class="ad-w" style="max-width:620px">
<h1>⚙️ ভোটার তালিকা সাইট — প্রথম সেটআপ</h1>
<?php if (!empty($already)): ?>
  <div class="ad-card ok">সাইট আগেই সেটআপ করা আছে। নিরাপত্তার জন্য cPanel File Manager থেকে <b>install.php</b> ফাইলটি মুছে দিন। <a href="login.php">লগইন পেজে যান →</a></div>
<?php elseif ($done): ?>
  <div class="ad-card ok"><b>✅ সেটআপ সম্পন্ন!</b><br>এখন যা করবেন:<ol>
    <li>cPanel File Manager থেকে <b>install.php</b> মুছে দিন।</li>
    <li><a href="login.php">লগইন করুন</a> → অ্যাডমিন প্যানেল → “ভোটার ডেটা আমদানি” থেকে আপনার পুরোনো index.html ফাইলটি দিন।</li></ol></div>
  <?php if ($manual): ?>
  <div class="ad-card warn"><b>config.php স্বয়ংক্রিয়ভাবে লেখা যায়নি।</b> File Manager এ <code>inc</code> ফোল্ডারে <code>config.php</code> নামে নতুন ফাইল বানিয়ে নিচের লেখাটি হুবহু পেস্ট করুন:
  <textarea readonly rows="9" style="width:100%;font-family:monospace"><?= h($manual) ?></textarea></div>
  <?php endif; ?>
<?php else: ?>
  <p class="mu">cPanel → <b>MySQL® Database Wizard</b> থেকে একটি ডাটাবেস ও ইউজার বানিয়ে (ALL PRIVILEGES দিয়ে) নিচে তথ্য দিন।</p>
  <?php if ($err): ?><div class="ad-card err"><?= h($err) ?></div><?php endif; ?>
  <form method="post" class="ad-card ad-form">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <h3>১. ডাটাবেস</h3>
    <label>ডাটাবেস হোস্ট<input name="db_host" value="<?= h($f['db_host']) ?>" required></label>
    <label>ডাটাবেসের নাম (যেমন: cpuser_voter)<input name="db_name" value="<?= h($f['db_name']) ?>" required></label>
    <label>ডাটাবেস ইউজার (যেমন: cpuser_voter)<input name="db_user" value="<?= h($f['db_user']) ?>" required autocomplete="off"></label>
    <label>ডাটাবেস পাসওয়ার্ড<input name="db_pass" type="password" autocomplete="new-password"></label>
    <h3>২. অ্যাডমিন অ্যাকাউন্ট</h3>
    <label>ইউজারনেম<input name="admin_user" value="<?= h($f['admin_user']) ?>" required pattern="[A-Za-z0-9_.@\-]{3,50}"></label>
    <label>পূর্ণ নাম<input name="admin_name" value="<?= h($f['admin_name']) ?>"></label>
    <label>পাসওয়ার্ড (কমপক্ষে ৮ অক্ষর)<input name="admin_pass" type="password" minlength="8" required autocomplete="new-password"></label>
    <label>পাসওয়ার্ড আবার<input name="admin_pass2" type="password" minlength="8" required autocomplete="new-password"></label>
    <button class="ad-btn">সেটআপ করুন</button>
  </form>
<?php endif; ?>
</main></body></html>

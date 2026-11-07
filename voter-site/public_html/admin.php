<?php
/* অ্যাডমিন প্যানেল: ইউজার, ডেটা আমদানি, সেটিংস, কার্যকলাপ */
require __DIR__ . '/inc/bootstrap.php';
send_security_headers();
$U = require_admin(isset($_GET['act']));
$db = db();
$msg = ''; $err = '';

/* ---------- ডেটা আমদানি (JSON API, ব্রাউজার থেকে খণ্ড খণ্ড করে পাঠানো হয়) ---------- */
if (isset($_GET['act'])) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['error' => 'POST only'], 405);
    $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($tok) || !hash_equals(csrf_token(), $tok)) json_out(['error' => 'csrf'], 419);
    $in = json_decode(file_get_contents('php://input'), true);
    @set_time_limit(120);
    switch ($_GET['act']) {
    case 'begin':
        $db->exec('DELETE FROM voters');
        $db->exec('ALTER TABLE voters AUTO_INCREMENT = 1');
        $db->exec('DELETE FROM sections');
        $ins = $db->prepare('INSERT INTO sections (id, gender, area_no, area_name, ward) VALUES (?,?,?,?,?)');
        foreach ((array)($in['s'] ?? []) as $i => $s) {
            if (!is_array($s) || count($s) < 4) json_out(['error' => 'sections ফরম্যাট ভুল'], 400);
            $ins->execute([$i, (string)$s[0], (string)$s[1], (string)$s[2], (string)$s[3]]);
        }
        log_activity((int)$U['id'], 'import', 'শুরু');
        json_out(['ok' => true]);
    case 'rows':
        $sec = [];
        foreach ($db->query('SELECT * FROM sections') as $s) $sec[(int)$s['id']] = $s;
        $rows = (array)($in['r'] ?? []);
        if (count($rows) > 5000) json_out(['error' => 'একবারে বেশি সারি'], 400);
        $SK = '/^(মোঃ|মোছাঃ|মোসাঃ|মোছা:|মৃত|মোসাম্মৎ|মোছাম্মৎ|মো:|মোছাম্মদ)$/u';
        $cols = 'sl,vno,name,father,mother,prof,dob,dob_date,addr,section_id,page,gender,ward,area_no,first_name,last_name,house,n_name,n_father,n_mother,n_addr,n_dob,n_prof,n_all';
        $vals = []; $ph = [];
        $one = '(' . implode(',', array_fill(0, 24, '?')) . ')';
        $db->beginTransaction();
        try {
            foreach ($rows as $r) {
                if (!is_array($r) || count($r) < 10) continue;
                [$sl, $vno, $name, $father, $mother, $prof, $dob, $addr, $secId, $page] = $r;
                $s = $sec[(int)$secId] ?? null;
                if (!$s) json_out(['error' => "অজানা এলাকা নং $secId"], 400);
                $name = trim((string)$name); $father = trim((string)$father); $mother = trim((string)$mother);
                $prof = trim((string)$prof); $dob = trim((string)$dob); $addr = trim((string)$addr); $vno = trim((string)$vno);
                $tk = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
                $first = ''; foreach ($tk as $t) if (!preg_match($SK, $t)) { $first = $t; break; }
                $last = count($tk) > 1 ? end($tk) : '';
                $house = trim(explode(',', $addr)[0]);
                $n = [nz($name), nz($father), nz($mother), nz($addr), nz($dob), nz($prof)];
                $all = "$n[0]|$n[1]|$n[2]|$n[3]|$vno|$n[4]|$n[5]";
                array_push($vals, (int)$sl, $vno, $name, $father, $mother, $prof, $dob, parse_dob($dob), $addr, (int)$secId, (int)$page,
                    $s['gender'], $s['ward'], $s['area_no'], mb_substr($first, 0, 60), mb_substr($last, 0, 60), mb_substr($house, 0, 150),
                    $n[0], $n[1], $n[2], $n[3], $n[4], $n[5], $all);
                $ph[] = $one;
                if (count($ph) >= 250) { $db->prepare("INSERT INTO voters ($cols) VALUES " . implode(',', $ph))->execute($vals); $vals = []; $ph = []; }
            }
            if ($ph) $db->prepare("INSERT INTO voters ($cols) VALUES " . implode(',', $ph))->execute($vals);
            $db->commit();
        } catch (Throwable $e) { $db->rollBack(); json_out(['error' => $e->getMessage()], 500); }
        json_out(['ok' => true, 'n' => count($rows)]);
    case 'finish':
        $db->exec('UPDATE voters SET dup = 0');
        $db->exec('UPDATE voters v JOIN (SELECT n_name, n_father, n_dob FROM voters GROUP BY n_name, n_father, n_dob HAVING COUNT(*) > 1) d
                   ON d.n_name = v.n_name AND d.n_father = v.n_father AND d.n_dob = v.n_dob SET v.dup = 1');
        $n = (int)$db->query('SELECT COUNT(*) FROM voters')->fetchColumn();
        log_activity((int)$U['id'], 'import', "সম্পন্ন: $n জন");
        json_out(['ok' => true, 'total' => $n]);
    }
    json_out(['error' => 'unknown'], 400);
}

/* ---------- সাধারণ ফর্ম ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $do = $_POST['do'] ?? '';
    $uid = (int)($_POST['uid'] ?? 0);
    try {
        if ($do === 'add_user') {
            $un = trim((string)$_POST['username']); $pw = (string)$_POST['password'];
            if (!preg_match('/^[A-Za-z0-9_.@-]{3,50}$/', $un)) $err = 'ইউজারনেম ৩-৫০ অক্ষর (ইংরেজি অক্ষর/সংখ্যা/._-@)।';
            elseif (strlen($pw) < 6) $err = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষর।';
            else {
                $db->prepare('INSERT INTO users (username, full_name, pass_hash, role) VALUES (?,?,?,?)')
                   ->execute([$un, trim((string)$_POST['full_name']), password_hash($pw, PASSWORD_DEFAULT), ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user']);
                $msg = "ইউজার “{$un}” যোগ হয়েছে।";
            }
        } elseif ($do === 'reset_pw') {
            $pw = (string)$_POST['password'];
            if (strlen($pw) < 6) $err = 'পাসওয়ার্ড কমপক্ষে ৬ অক্ষর।';
            else {
                $db->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), $uid]);
                $db->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$uid]);
                $msg = 'পাসওয়ার্ড পরিবর্তন হয়েছে।';
            }
        } elseif ($do === 'toggle' && $uid !== (int)$U['id']) {
            $db->prepare('UPDATE users SET active = 1 - active WHERE id = ?')->execute([$uid]);
            $db->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$uid]);
            $msg = 'অবস্থা পরিবর্তন হয়েছে।';
        } elseif ($do === 'delete' && $uid !== (int)$U['id']) {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            $msg = 'ইউজার মুছে ফেলা হয়েছে।';
        } elseif ($do === 'settings') {
            $st = $db->prepare('REPLACE INTO settings (k, v) VALUES (?,?)');
            foreach (array_keys(DEFAULT_SETTINGS) as $k) $st->execute([$k, trim((string)($_POST[$k] ?? ''))]);
            $msg = 'সেটিংস সংরক্ষিত হয়েছে।';
        } elseif ($do === 'clear_log') {
            $db->exec('DELETE FROM activity_log WHERE at < NOW() - INTERVAL 30 DAY');
            $msg = '৩০ দিনের পুরোনো কার্যকলাপ মুছে ফেলা হয়েছে।';
        }
    } catch (PDOException $e) {
        $err = $e->getCode() == 23000 ? 'এই ইউজারনেম আগে থেকেই আছে।' : 'ত্রুটি: ' . $e->getMessage();
    }
    if (!$err) { $_SESSION['flash'] = $msg; header('Location: admin.php?tab=' . urlencode($_POST['tab'] ?? '')); exit; }
}
if (!empty($_SESSION['flash'])) { $msg = $_SESSION['flash']; unset($_SESSION['flash']); }

$tab = $_GET['tab'] ?? 'home';
$S = settings();
$stats = $db->query("SELECT COUNT(*) n, SUM(gender='মহিলা') f, SUM(gender='পুরুষ') m, SUM(dup) d FROM voters")->fetch();
$users = $db->query('SELECT id, username, full_name, role, active, created_at, last_login FROM users ORDER BY id')->fetchAll();
$log = $db->query('SELECT l.*, u.username FROM activity_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 200')->fetchAll();
$actName = ['login' => 'লগইন', 'logout' => 'লগআউট', 'search' => 'অনুসন্ধান', 'print' => 'প্রিন্ট', 'csv' => 'CSV ডাউনলোড', 'import' => 'আমদানি'];
$tabs = ['home' => '🏠 ড্যাশবোর্ড', 'users' => '👥 ইউজার', 'import' => '⬆ ভোটার ডেটা আমদানি', 'settings' => '⚙ সেটিংস', 'log' => '📜 কার্যকলাপ'];
if (!isset($tabs[$tab])) $tab = 'home';
?><!DOCTYPE html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>অ্যাডমিন — <?= h($S['shop_name']) ?></title><link rel="stylesheet" href="assets/admin.css?v=2"></head><body>
<header class="ad-h"><div class="ad-w"><b>⚙ অ্যাডমিন প্যানেল — <?= h($S['shop_name']) ?></b><span><a href="index.php">← ভোটার অনুসন্ধান</a></span></div></header>
<main class="ad-w">
<nav class="ad-tabs"><?php foreach ($tabs as $k => $v): ?><a href="?tab=<?= $k ?>" class="<?= $k === $tab ? 'on' : '' ?>"><?= $v ?></a><?php endforeach; ?></nav>
<?php if ($msg): ?><div class="ad-card ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="ad-card err"><?= h($err) ?></div><?php endif; ?>

<?php if ($tab === 'home'): ?>
<div class="ad-kp">
  <div><b><?= bn(number_format((int)$stats['n'])) ?></b><span>মোট ভোটার</span></div>
  <div><b><?= bn(number_format((int)$stats['f'])) ?></b><span>মহিলা</span></div>
  <div><b><?= bn(number_format((int)$stats['m'])) ?></b><span>পুরুষ</span></div>
  <div><b><?= bn(number_format((int)$stats['d'])) ?></b><span>ডুপ্লিকেট সন্দেহ</span></div>
  <div><b><?= bn(count($users)) ?></b><span>ইউজার</span></div>
</div>
<?php if (!(int)$stats['n']): ?><div class="ad-card warn">ডাটাবেসে এখনো কোনো ভোটার নেই। <a href="?tab=import">ডেটা আমদানি করুন →</a></div><?php endif; ?>
<div class="ad-card"><h3>সাম্প্রতিক কার্যকলাপ</h3><table class="ad-t"><tr><th>সময়</th><th>ইউজার</th><th>কাজ</th><th>বিবরণ</th></tr>
<?php foreach (array_slice($log, 0, 15) as $l): ?><tr><td><?= h($l['at']) ?></td><td><?= h($l['username'] ?? '—') ?></td><td><?= h($actName[$l['action']] ?? $l['action']) ?></td><td><?= h($l['detail']) ?></td></tr><?php endforeach; ?>
</table></div>

<?php elseif ($tab === 'users'): ?>
<div class="ad-card"><h3>নতুন ইউজার যোগ করুন</h3>
<form method="post" class="ad-inline"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="do" value="add_user"><input type="hidden" name="tab" value="users">
<input name="username" placeholder="ইউজারনেম" required pattern="[A-Za-z0-9_.@\-]{3,50}"><input name="full_name" placeholder="পূর্ণ নাম"><input name="password" type="password" placeholder="পাসওয়ার্ড" required minlength="6" autocomplete="new-password">
<select name="role"><option value="user">সাধারণ ইউজার</option><option value="admin">অ্যাডমিন</option></select><button class="ad-btn">যোগ করুন</button></form>
<p class="mu">সাধারণ ইউজার শুধু খুঁজতে ও একজনের তথ্য প্রিন্ট করতে পারবে। অ্যাডমিন সব ফিল্টার, CSV ও এই প্যানেল ব্যবহার করতে পারবে।</p></div>
<div class="ad-card"><table class="ad-t"><tr><th>ইউজারনেম</th><th>নাম</th><th>ধরন</th><th>অবস্থা</th><th>শেষ লগইন</th><th>কাজ</th></tr>
<?php foreach ($users as $u): $self = (int)$u['id'] === (int)$U['id']; ?>
<tr class="<?= $u['active'] ? '' : 'off' ?>"><td><b><?= h($u['username']) ?></b><?= $self ? ' (আপনি)' : '' ?></td><td><?= h($u['full_name']) ?></td><td><?= $u['role'] === 'admin' ? 'অ্যাডমিন' : 'ইউজার' ?></td>
<td><?= $u['active'] ? '✅ চালু' : '⛔ বন্ধ' ?></td><td><?= h($u['last_login'] ?? '—') ?></td><td class="ad-acts">
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="tab" value="users"><input type="hidden" name="do" value="reset_pw"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>"><input name="password" type="password" placeholder="নতুন পাসওয়ার্ড" minlength="6" required autocomplete="new-password"><button class="ad-btn sm">পাসওয়ার্ড বদলান</button></form>
<?php if (!$self): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="tab" value="users"><input type="hidden" name="do" value="toggle"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>"><button class="ad-btn sm gray"><?= $u['active'] ? 'বন্ধ করুন' : 'চালু করুন' ?></button></form>
<form method="post" onsubmit="return confirm('এই ইউজার মুছে ফেলবেন?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="tab" value="users"><input type="hidden" name="do" value="delete"><input type="hidden" name="uid" value="<?= (int)$u['id'] ?>"><button class="ad-btn sm red">মুছুন</button></form>
<?php endif; ?></td></tr>
<?php endforeach; ?></table></div>

<?php elseif ($tab === 'import'): ?>
<div class="ad-card"><h3>ভোটার ডেটা আমদানি</h3>
<p>আপনার পুরোনো <b>index.html</b> ফাইলটি (যেটায় সব ভোটারের তথ্য আছে) এখানে দিন। অথবা <code>{"s":[...],"r":[...]}</code> ফরম্যাটের JSON ফাইল।</p>
<p class="warn-t">⚠ আমদানি করলে ডাটাবেসের আগের সব ভোটার মুছে নতুন তালিকা বসবে। (ইউজার ও সেটিংস থাকবে।)</p>
<input type="file" id="imf" accept=".html,.htm,.json"> <button class="ad-btn" id="imb">আমদানি শুরু করুন</button>
<div class="ad-prog hide" id="imp"><i id="imbar"></i></div><div id="ims" class="mu"></div></div>
<script>
(function(){
const CSRF=<?= json_encode(csrf_token()) ?>,st=document.getElementById('ims'),bar=document.getElementById('imbar'),btn=document.getElementById('imb');
async function post(act,body){const r=await fetch('admin.php?act='+act,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF},body:JSON.stringify(body)});let j;try{j=await r.json()}catch(e){throw new Error('সার্ভার উত্তর বুঝা যায়নি ('+r.status+')')}if(!r.ok||j.error)throw new Error(j.error||r.status);return j}
async function readData(file){
 const txt=await file.text();
 if(/^\s*\{/.test(txt))return JSON.parse(txt);
 const m=txt.match(/const D="([A-Za-z0-9+/=]+)"/);if(!m)throw new Error('ফাইলে ভোটার ডেটা (const D="...") পাওয়া যায়নি');
 const raw=atob(m[1]),u=new Uint8Array(raw.length);for(let i=0;i<raw.length;i++)u[i]=raw.charCodeAt(i);
 return JSON.parse(await new Response(new Blob([u]).stream().pipeThrough(new DecompressionStream('gzip'))).text());
}
btn.onclick=async()=>{
 const f=document.getElementById('imf').files[0];if(!f){alert('আগে ফাইল বাছাই করুন');return}
 if(!confirm('আগের সব ভোটার তথ্য মুছে নতুন তালিকা আমদানি হবে। চালিয়ে যাবেন?'))return;
 btn.disabled=true;document.getElementById('imp').classList.remove('hide');
 try{
  st.textContent='ফাইল পড়া হচ্ছে…';const J=await readData(f);
  if(!Array.isArray(J.s)||!Array.isArray(J.r))throw new Error('ফাইলের ফরম্যাট ঠিক নয় (s ও r দরকার)');
  await post('begin',{s:J.s});
  const C=1000;for(let i=0;i<J.r.length;i+=C){await post('rows',{r:J.r.slice(i,i+C)});const d=Math.min(J.r.length,i+C);bar.style.width=(d*100/J.r.length)+'%';st.textContent=`আমদানি হচ্ছে… ${d.toLocaleString('bn-BD')} / ${J.r.length.toLocaleString('bn-BD')}`}
  st.textContent='ডুপ্লিকেট যাচাই হচ্ছে…';const z=await post('finish',{});
  st.innerHTML='✅ সম্পন্ন! মোট '+z.total.toLocaleString('bn-BD')+' জন ভোটার ডাটাবেসে আছে। <a href="index.php">অনুসন্ধানে যান →</a><br>⚠ এখন পুরোনো index.html ফাইলটি হোস্টিং থেকে মুছে দিন (থাকলে)।';
 }catch(e){st.textContent='❌ ত্রুটি: '+e.message+' — আবার চেষ্টা করুন।'}
 btn.disabled=false;
};
})();
</script>

<?php elseif ($tab === 'settings'): ?>
<form method="post" class="ad-card ad-form"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="do" value="settings"><input type="hidden" name="tab" value="settings">
<h3>সাইটের লেখা</h3>
<?php foreach (['site_title' => 'শিরোনাম', 'subtitle' => 'উপশিরোনাম (ইউনিয়ন, তালিকার তথ্য)', 'shop_name' => 'দোকানের নাম (প্রিন্টে আসবে)', 'shop_addr' => 'দোকানের ঠিকানা (প্রিন্টে আসবে)', 'credit' => 'লগইন পেজের নিচের নাম'] as $k => $l): ?>
<label><?= $l ?><input name="<?= $k ?>" value="<?= h($S[$k]) ?>"></label>
<?php endforeach; ?><button class="ad-btn">সংরক্ষণ করুন</button></form>

<?php elseif ($tab === 'log'): ?>
<div class="ad-card"><h3>সর্বশেষ ২০০ কার্যকলাপ</h3>
<form method="post" onsubmit="return confirm('৩০ দিনের পুরোনো রেকর্ড মুছবেন?')"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><input type="hidden" name="do" value="clear_log"><input type="hidden" name="tab" value="log"><button class="ad-btn sm gray">৩০ দিনের পুরোনো মুছুন</button></form>
<table class="ad-t"><tr><th>সময়</th><th>ইউজার</th><th>কাজ</th><th>বিবরণ</th><th>IP</th></tr>
<?php foreach ($log as $l): ?><tr><td><?= h($l['at']) ?></td><td><?= h($l['username'] ?? '—') ?></td><td><?= h($actName[$l['action']] ?? $l['action']) ?></td><td><?= h($l['detail']) ?></td><td><?= h($l['ip']) ?></td></tr><?php endforeach; ?>
</table></div>
<?php endif; ?>
</main></body></html>

<?php
require __DIR__ . '/inc/bootstrap.php';
send_security_headers();
$U = require_login();
$S = settings();
$isAdmin = $U['role'] === 'admin';
$logo = '<svg viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="5" y="7" width="38" height="25" rx="4" stroke="#fff" stroke-width="3"/><path d="M17 40h14M24 32v8" stroke="#fff" stroke-width="3" stroke-linecap="round"/><path d="M15 25V14.5l4.5 5.5L24 14.5 28.5 20 33 14.5V25" stroke="#7fc4ff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
?><!DOCTYPE html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($S['site_title']) ?></title><link rel="stylesheet" href="assets/app.css?v=2"><link rel="stylesheet" href="assets/extra.css?v=2"></head>
<body class="<?= $isAdmin ? 'adm' : '' ?>">
<header><div class="mc-brand"><div class="mc-logo"><?= $logo ?></div><h2 class="mc-name"><span><?= h($S['shop_name']) ?></span></h2></div><div class="w"><div><h1>🗳️ <?= h($S['site_title']) ?></h1><small><?= h($S['subtitle']) ?></small></div>
<span class="hb"><?php if ($isAdmin): ?><a class="chip" href="admin.php">⚙ অ্যাডমিন</a><?php endif; ?>
<form method="post" action="logout.php" id="lo"><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><button type="submit" class="chip" id="logoutBtn" title="<?= h($U['full_name']) ?>">⎋ লগ আউট (<?= h($U['username']) ?>)</button></form>
<span class="chip" id="tot">লোড হচ্ছে…</span></span></div></header>
<div class="w"><div id="ld">⏳ ভোটারের তথ্য লোড হচ্ছে…</div>
<div id="app" class="hide">
<div class="card fl"><label for="q">অনুসন্ধান (নাম, পিতা/মাতার নাম, জন্ম তারিখ/সাল, ঠিকানা, ভোটার নং — একাধিক শব্দ লিখলে সবগুলো মিলবে)</label>
<div class="qw"><input id="q" placeholder="যেমন: মোঃ আলী হোসেন  |  ০১/১১/১৯৯৮  |  আসলাম হাজীর বাড়ী  |  ৭৫০৩০৭..." autocomplete="off"></div>
<div class="fg" style="margin-top:10px">
<div><label>খোঁজার ক্ষেত্র</label><select id="sc"><option value="all">সব ক্ষেত্রে</option><option value="n">শুধু নাম</option><option value="f">পিতার নাম</option><option value="m">মাতার নাম</option><option value="a">ঠিকানা</option><option value="v">ভোটার নং</option><option value="d">জন্ম তারিখ</option><option value="p">পেশা</option></select></div>
<div><label>ওয়ার্ড নং</label><select id="w"></select></div><div><label>ভোটার এলাকা</label><select id="a"></select></div>
<div><label>লিঙ্গ</label><select id="g"><option value="">সব</option><option>মহিলা</option><option>পুরুষ</option><option>হিজড়া</option></select></div>
<div class="rm"><label>পেশা</label><select id="p"></select></div><div class="rm"><label>বয়স (থেকে)</label><input id="a1" type="number" min="18" placeholder="১৮"></div><div class="rm"><label>বয়স (পর্যন্ত)</label><input id="a2" type="number" placeholder="১২০"></div>
<div class="rm"><label>সাজান</label><select id="so"><option value="">তালিকার ক্রম</option><option value="n">নাম (অ-হ)</option><option value="ad">বয়স: বড় → ছোট</option><option value="aa">বয়স: ছোট → বড়</option></select></div></div>
<div class="ck"><label class="rm"><input type="checkbox" id="c1"> শুধু ০১/০১ জন্ম তারিখ</label><label class="rm"><input type="checkbox" id="c2"> সন্দেহজনক ডুপ্লিকেট (নাম+পিতা+জন্ম তারিখ এক)</label><label class="rm"><input type="checkbox" id="c3"> পিতা/মাতা "মৃত" লেখা</label>
<button class="rm" id="rs">↺ রিসেট</button><button class="rm bp" id="ex">⬇ ফলাফল CSV</button><button class="rm" id="pr">🖨 প্রিন্ট</button></div></div>
<div class="tabs"><button id="t1" class="on">🔍 অনুসন্ধান ফলাফল</button><button id="t2">📊 বিশ্লেষণ</button></div>
<div id="v1" class="card" style="margin-top:0;border-radius:0 12px 12px 12px"><div class="kp" id="ks"></div><div class="tw" style="margin-top:12px"><table><thead><tr><th>#</th><th>নাম</th><th>ভোটার নং</th><th>পিতা</th><th>মাতা</th><th>জন্ম তারিখ (বয়স)</th><th>পেশা</th><th>ওয়ার্ড/এলাকা</th><th>ঠিকানা</th></tr></thead><tbody id="tb"></tbody></table></div><div class="pg"><button id="pv">← আগের</button><span id="pi"></span><button id="nx">পরের →</button></div></div>
<div id="v2" class="hide"><div class="card"><div class="kp" id="k2"></div></div><div class="g2" id="ch"></div></div>
</div></div>
<dialog id="dg"><div id="dgc"></div><div class="dgb"><button type="button" id="dgPrint">🖨 প্রিন্ট / পিডিএফ</button><button type="button" id="dgClose" class="bp">বন্ধ করুন</button></div></dialog>
<div id="prt" aria-hidden="true"></div>
<button id="themeBtn" type="button" aria-label="লাইট/ডার্ক মোড">🌙</button>
<script>window.APP=<?= json_encode(['shop' => $S['shop_name'], 'addr' => $S['shop_addr'], 'admin' => $isAdmin], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="assets/app.js?v=2"></script>
<script src="assets/print.js?v=2"></script>
</body></html>

<?php
/* JSON API: meta, search, ana, csv */
require __DIR__ . '/inc/bootstrap.php';
send_security_headers();
$U = require_login(true);
$db = db();
$act = $_GET['act'] ?? '';
const PS = 50;
const AGE = 'TIMESTAMPDIFF(YEAR, dob_date, CURDATE())';

/* ফিল্টার → SQL WHERE */
function build_where(): array {
    $w = []; $p = [];
    $cols = ['all' => 'n_all', 'n' => 'n_name', 'f' => 'n_father', 'm' => 'n_mother', 'a' => 'n_addr', 'v' => 'vno', 'd' => 'n_dob', 'p' => 'n_prof'];
    $col = $cols[$_GET['sc'] ?? 'all'] ?? 'n_all';
    $terms = array_slice(array_filter(preg_split('/\s+/u', trim((string)($_GET['q'] ?? '')))), 0, 8);
    foreach ($terms as $t) {
        $t = nz($t);
        if ($t === '') continue;
        $w[] = "$col LIKE ?";
        $p[] = '%' . addcslashes($t, '%_\\') . '%';
    }
    foreach (['w' => 'ward', 'a' => 'area_no', 'g' => 'gender', 'p' => 'prof'] as $k => $c) {
        if (isset($_GET[$k]) && $_GET[$k] !== '') { $w[] = "$c = ?"; $p[] = (string)$_GET[$k]; }
    }
    $a1 = (int)($_GET['a1'] ?? 0); $a2 = (int)($_GET['a2'] ?? 0);
    if ($a1 > 0) { $w[] = AGE . ' >= ?'; $p[] = $a1; }
    if ($a2 > 0) { $w[] = AGE . ' <= ?'; $p[] = $a2; }
    if (!empty($_GET['c1'])) $w[] = "dob LIKE '০১/০১%'";
    if (!empty($_GET['c2'])) $w[] = 'dup = 1';
    if (!empty($_GET['c3'])) $w[] = "(father LIKE 'মৃত%' OR mother LIKE 'মৃত%')";
    return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
}

function order_by(): string {
    switch ($_GET['so'] ?? '') {
        case 'n':  return 'ORDER BY name COLLATE utf8mb4_bin, id';
        case 'ad': return 'ORDER BY dob_date IS NULL, dob_date, id';
        case 'aa': return 'ORDER BY dob_date IS NULL, dob_date DESC, id';
        default:   return 'ORDER BY id';
    }
}

function q(string $sql, array $p = []): PDOStatement { $st = db()->prepare($sql); $st->execute($p); return $st; }

const ROWCOLS = 'id i, sl, vno, name, father, mother, prof, dob, addr, page, gender g, ward w, area_no an, dup, '
    . AGE . ' age, (SELECT area_name FROM sections s WHERE s.id = voters.section_id) area';

switch ($act) {
case 'meta':
    $S = $db->query('SELECT id, gender, area_no, area_name, ward FROM sections ORDER BY id')->fetchAll();
    $prof = $db->query("SELECT prof, COUNT(*) c FROM voters GROUP BY prof ORDER BY c DESC")->fetchAll(PDO::FETCH_NUM);
    $tot = (int)$db->query('SELECT COUNT(*) FROM voters')->fetchColumn();
    json_out(['sections' => $S, 'prof' => $prof, 'total' => $tot]);

case 'search':
    [$where, $p] = build_where();
    $pg = max(0, (int)($_GET['pg'] ?? 0));
    $k = q("SELECT COUNT(*) n, SUM(gender='মহিলা') f, SUM(gender='পুরুষ') m, AVG(" . AGE . ") av FROM voters $where", $p)->fetch();
    $rows = q('SELECT ' . ROWCOLS . " FROM voters $where " . order_by() . ' LIMIT ' . PS . ' OFFSET ' . ($pg * PS), $p)->fetchAll();
    if ($pg === 0 && ($_GET['q'] ?? '') !== '') log_activity((int)$U['id'], 'search', (string)$_GET['q']);
    json_out(['n' => (int)$k['n'], 'f' => (int)$k['f'], 'm' => (int)$k['m'], 'av' => (float)$k['av'], 'ps' => PS, 'rows' => $rows]);

case 'ana':
    [$where, $p] = build_where();
    $A = AGE;
    $k = q("SELECT COUNT(*) n, SUM(gender='মহিলা') f, AVG($A) av, SUM($A<30) yg, SUM($A>=60) sr, SUM($A>=90) n90,
        SUM(dob LIKE '০১/০১%') d11, SUM(dup) dup,
        SUM($A BETWEEN 18 AND 25) b1, SUM($A BETWEEN 26 AND 35) b2, SUM($A BETWEEN 36 AND 45) b3,
        SUM($A BETWEEN 46 AND 60) b4, SUM($A BETWEEN 61 AND 75) b5, SUM($A >= 76) b6 FROM voters $where", $p)->fetch();
    $n = (int)$k['n'];
    $med = $n ? q("SELECT $A FROM voters $where ORDER BY $A LIMIT 1 OFFSET " . ($n >> 1), $p)->fetchColumn() : null;
    $ward = q("SELECT ward, COUNT(*) n, SUM(gender='মহিলা') f, SUM(gender='পুরুষ') m FROM voters $where GROUP BY ward", $p)->fetchAll(PDO::FETCH_NUM);
    $dec = q("SELECT FLOOR(YEAR(dob_date)/10)*10 d, COUNT(*) c FROM voters $where" . ($where ? ' AND' : ' WHERE') . " dob_date IS NOT NULL GROUP BY d ORDER BY d", $p)->fetchAll(PDO::FETCH_NUM);
    $top = function (string $col, int $lim) use ($where, $p) {
        $and = $where ? "$where AND $col <> ''" : "WHERE $col <> ''";
        return q("SELECT $col, COUNT(*) c FROM voters $and GROUP BY $col ORDER BY c DESC LIMIT $lim", $p)->fetchAll(PDO::FETCH_NUM);
    };
    $prof = q("SELECT IF(prof='', '(ফাঁকা)', prof) x, COUNT(*) c FROM voters $where GROUP BY x ORDER BY c DESC LIMIT 10", $p)->fetchAll(PDO::FETCH_NUM);
    $area = q("SELECT area_no, COUNT(*) c FROM voters $where GROUP BY area_no ORDER BY c DESC LIMIT 12", $p)->fetchAll(PDO::FETCH_NUM);
    json_out(['k' => $k, 'med' => $med, 'ward' => $ward, 'dec' => $dec, 'prof' => $prof,
        'fn' => $top('first_name', 10), 'ln' => $top('last_name', 10), 'hh' => $top('house', 10), 'area' => $area]);

case 'csv':
    if ($U['role'] !== 'admin') json_out(['error' => 'forbidden'], 403);
    [$where, $p] = build_where();
    log_activity((int)$U['id'], 'csv', (string)($_GET['q'] ?? ''));
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="voter_search_result.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['ওয়ার্ড', 'ভোটার এলাকা', 'লিঙ্গ', 'ক্রমিক', 'নাম', 'ভোটার নং', 'পিতা', 'মাতা', 'পেশা', 'জন্ম তারিখ', 'বয়স', 'ঠিকানা', 'PDF পৃষ্ঠা']);
    $st = q('SELECT ' . ROWCOLS . " FROM voters $where " . order_by(), $p);
    while ($r = $st->fetch()) fputcsv($out, [$r['w'], $r['area'], $r['g'], $r['sl'], $r['name'], $r['vno'], $r['father'], $r['mother'], $r['prof'], $r['dob'], $r['age'], $r['addr'], $r['page']]);
    exit;

case 'print':
    log_activity((int)$U['id'], 'print', (string)($_GET['vno'] ?? ''));
    json_out(['ok' => true]);

default:
    json_out(['error' => 'unknown action'], 400);
}

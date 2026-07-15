<?php
/* English Line — API profili e sincronizzazione (PHP + SQLite) */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

function out($arr, $code = 200) { http_response_code($code); echo json_encode($arr); exit; }

$dbFile = __DIR__ . '/data/el.sqlite';
if (!file_exists($dbFile)) out(['error' => 'Database non inizializzato: apri /install.php'], 500);
try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=5000;');
} catch (Exception $e) { out(['error' => 'DB non disponibile'], 500); }

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $body['action'] ?? '';

function bearerToken() {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    return preg_match('/Bearer\s+([a-f0-9]{64})/i', $h, $m) ? $m[1] : null;
}
function authUser($db) {
    $t = bearerToken();
    if (!$t) out(['error' => 'Non autenticato'], 401);
    $q = $db->prepare('SELECT u.id, u.email FROM tokens t JOIN users u ON u.id=t.user_id WHERE t.token=?');
    $q->execute([$t]);
    $u = $q->fetch(PDO::FETCH_ASSOC);
    if (!$u) out(['error' => 'Sessione scaduta: rifai il login'], 401);
    return $u;
}
function makeToken($db, $uid) {
    $t = bin2hex(random_bytes(32));
    $db->prepare('INSERT INTO tokens(token, user_id, created_at) VALUES(?,?,?)')->execute([$t, $uid, time()]);
    // tiene al massimo 10 sessioni per utente
    $db->prepare('DELETE FROM tokens WHERE user_id=? AND token NOT IN (SELECT token FROM tokens WHERE user_id=? ORDER BY created_at DESC LIMIT 10)')->execute([$uid, $uid]);
    return $t;
}
function stateOf($db, $uid) {
    $q = $db->prepare('SELECT data, updated_at FROM states WHERE user_id=?');
    $q->execute([$uid]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    return $r ? ['data' => json_decode($r['data'], true), 'updated_at' => (int)$r['updated_at']] : ['data' => null, 'updated_at' => 0];
}

if ($action === 'register') {
    $email = strtolower(trim($body['email'] ?? ''));
    $pass  = $body['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(['error' => 'Email non valida'], 400);
    if (strlen($pass) < 6) out(['error' => 'La password deve avere almeno 6 caratteri'], 400);
    $q = $db->prepare('SELECT id FROM users WHERE email=?');
    $q->execute([$email]);
    if ($q->fetch()) out(['error' => 'Email già registrata: fai il login'], 409);
    $db->prepare('INSERT INTO users(email, pass_hash, created_at) VALUES(?,?,?)')
       ->execute([$email, password_hash($pass, PASSWORD_DEFAULT), time()]);
    $uid = (int)$db->lastInsertId();
    out(['token' => makeToken($db, $uid), 'email' => $email, 'data' => null, 'updated_at' => 0]);
}

if ($action === 'login') {
    $email = strtolower(trim($body['email'] ?? ''));
    $pass  = $body['password'] ?? '';
    $q = $db->prepare('SELECT id, pass_hash FROM users WHERE email=?');
    $q->execute([$email]);
    $u = $q->fetch(PDO::FETCH_ASSOC);
    if (!$u || !password_verify($pass, $u['pass_hash'])) out(['error' => 'Email o password sbagliata'], 401);
    $st = stateOf($db, (int)$u['id']);
    out(['token' => makeToken($db, (int)$u['id']), 'email' => $email, 'data' => $st['data'], 'updated_at' => $st['updated_at']]);
}

if ($action === 'load') {
    $u = authUser($db);
    $st = stateOf($db, (int)$u['id']);
    out(['email' => $u['email'], 'data' => $st['data'], 'updated_at' => $st['updated_at']]);
}

if ($action === 'save') {
    $u = authUser($db);
    $data = $body['data'] ?? null;
    if (!is_array($data)) out(['error' => 'Dati mancanti'], 400);
    $json = json_encode($data);
    if (strlen($json) > 2000000) out(['error' => 'Backup troppo grande'], 413);
    $now = time();
    $db->prepare('INSERT INTO states(user_id, data, updated_at) VALUES(?,?,?)
                  ON CONFLICT(user_id) DO UPDATE SET data=excluded.data, updated_at=excluded.updated_at')
       ->execute([(int)$u['id'], $json, $now]);
    out(['ok' => true, 'updated_at' => $now]);
}

if ($action === 'logout') {
    $t = bearerToken();
    if ($t) $db->prepare('DELETE FROM tokens WHERE token=?')->execute([$t]);
    out(['ok' => true]);
}

out(['error' => 'Azione sconosciuta'], 400);

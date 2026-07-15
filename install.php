<?php
/* English Line — installazione database (da aprire una volta sola) */
header('Content-Type: text/html; charset=utf-8');
$dir = __DIR__ . '/data';
$msgs = [];
$ok = true;
try {
    if (!is_dir($dir)) { mkdir($dir, 0755, true); $msgs[] = 'Cartella data/ creata'; }
    if (!file_exists($dir . '/.htaccess')) {
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
        $msgs[] = 'Protezione .htaccess sulla cartella data/';
    }
    $db = new PDO('sqlite:' . $dir . '/el.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        pass_hash TEXT NOT NULL,
        created_at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS tokens(
        token TEXT PRIMARY KEY,
        user_id INTEGER NOT NULL,
        created_at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS states(
        user_id INTEGER PRIMARY KEY,
        data TEXT NOT NULL,
        updated_at INTEGER NOT NULL)');
    $msgs[] = 'Tabelle users, tokens, states pronte';
    $n = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $msgs[] = "Utenti registrati: $n";
} catch (Exception $e) { $ok = false; $msgs[] = 'ERRORE: ' . $e->getMessage(); }
?>
<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><title>English Line — Install</title>
<style>body{font-family:system-ui;background:#F5F3ED;color:#12172B;display:grid;place-items:center;min-height:100vh;margin:0}
.card{background:#fff;border:1px solid #E6E2D8;border-radius:16px;padding:28px;max-width:460px;box-shadow:0 8px 24px -12px rgba(18,23,43,.18)}
h1{font-size:1.2rem;margin:0 0 12px}li{margin:6px 0}
.ok{color:#1F9254;font-weight:700}.no{color:#C0303A;font-weight:700}
a{color:#2F63B7}</style></head><body><div class="card">
<h1>English Line — installazione database</h1>
<ul><?php foreach ($msgs as $m) echo '<li>' . htmlspecialchars($m) . '</li>'; ?></ul>
<p class="<?= $ok ? 'ok' : 'no' ?>"><?= $ok ? 'Tutto pronto! Puoi usare i profili.' : 'Qualcosa è andato storto.' ?></p>
<p><a href="./">→ Vai all'app</a></p>
</div></body></html>

<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/bootstrap.php';
send_security_headers();
start_secure_session();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        http_response_code(400);
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $action = (string)($_POST['action'] ?? 'login');
        if ($action === 'logout' && admin_logged_in()) {
            $_SESSION = [];
            session_regenerate_id(true);
            session_destroy();
            header('Location: /admin.php');
            exit;
        }
        if ($action === 'delete' && admin_logged_in()) {
            $id = (string)($_POST['id'] ?? '');
            update_json_file('results.json', static fn(array $records): array => array_values(array_filter($records, static fn($row) => !isset($row['id']) || !hash_equals((string)$row['id'], $id))));
            header('Location: /admin.php?deleted=1');
            exit;
        }
        if ($action === 'login') {
            $config = admin_config();
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $key = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $attempts = read_json_file('login-attempts.json');
            $recent = array_values(array_filter($attempts[$key] ?? [], static fn($time) => is_int($time) && $time > time() - 900));
            if (count($recent) >= 5) {
                http_response_code(429);
                $error = 'Too many sign-in attempts. Wait 15 minutes, then try again.';
            } elseif (hash_equals($config['username'], $username) && password_verify($password, $config['password_hash'])) {
                unset($attempts[$key]);
                write_json_file('login-attempts.json', $attempts);
                session_regenerate_id(true);
                $_SESSION['admin_authenticated'] = true;
                $_SESSION['admin_username'] = $config['username'];
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                header('Location: /admin.php');
                exit;
            } else {
                $recent[] = time();
                $attempts[$key] = $recent;
                write_json_file('login-attempts.json', $attempts);
                $error = 'The username or password is incorrect.';
            }
        }
    }
}
$loggedIn = admin_logged_in();
$csrf = csrf_token();
$records = $loggedIn ? read_json_file('results.json') : [];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Admin · DataReadyTest.online</title>
<style>
:root{color-scheme:light;--ink:#14273a;--muted:#64758a;--line:#dce5ed;--blue:#1768b1;--canvas:#f3f7fa;--orange:#e9842d}*{box-sizing:border-box}body{margin:0;background:var(--canvas);color:var(--ink);font:15px/1.55 system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{max-width:1100px;margin:0 auto;padding:28px 20px 60px}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:26px;box-shadow:0 12px 35px #12314c10}h1{font-size:clamp(25px,4vw,36px);letter-spacing:-.03em;margin:0 0 5px}h2{font-size:20px;margin:0 0 16px}.muted{color:var(--muted)}.login{max-width:460px;margin:10vh auto}.field{margin:17px 0}.field label{display:block;font-weight:700;margin-bottom:6px}.field input{width:100%;padding:12px;border:1px solid #cbd7e1;border-radius:9px;font:inherit}.btn{border:0;border-radius:9px;padding:11px 16px;background:var(--blue);color:#fff;font:inherit;font-weight:700;cursor:pointer}.btn.secondary{background:#edf2f6;color:#334b62}.btn.danger{background:#fff0ed;color:#a13a2b}.error{padding:12px 14px;border-radius:9px;background:#fff1ee;color:#8e3024;margin:16px 0}.top{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin:0 0 22px}.top p{margin:4px 0 0}.actions{display:flex;gap:9px;align-items:center}.table-wrap{overflow:auto;margin-top:20px}table{width:100%;border-collapse:collapse;background:#fff}th,td{text-align:left;vertical-align:top;padding:13px 12px;border-bottom:1px solid #e8edf2}th{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#748294;background:#f8fafb}td small{display:block;color:var(--muted)}details summary{cursor:pointer;color:#195b92;font-weight:650}details .response{white-space:pre-wrap;overflow-wrap:anywhere;margin:10px 0;padding:12px;background:#f6f9fb;border-radius:8px;font-size:13px}.empty{padding:36px 18px;text-align:center;color:var(--muted)}.notice{padding:12px 14px;border-left:3px solid #4289bc;background:#f0f7fc;border-radius:8px;color:#3b5871;font-size:13px;margin:18px 0}@media(max-width:650px){.wrap{padding:19px 12px 40px}.card{padding:19px}.top{flex-direction:column}.actions{width:100%}table{min-width:740px}}
</style></head><body><main class="wrap">
<?php if (!$loggedIn): ?>
<section class="card login"><h1>Admin sign in</h1><p class="muted">View completed participant reports.</p><?php if ($error !== ''): ?><div class="error" role="alert"><?=html($error)?></div><?php endif; ?><form method="post" action="/admin.php"><input type="hidden" name="csrf" value="<?=html($csrf)?>"><input type="hidden" name="action" value="login"><div class="field"><label for="username">Username</label><input id="username" name="username" autocomplete="username" required></div><div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div><button class="btn" type="submit">Sign in</button></form></section>
<?php else: ?>
<header class="top"><div><h1>Assessment results</h1><p class="muted">Signed in as <?=html((string)$_SESSION['admin_username'])?> · <?=count($records)?> report<?=count($records)===1?'':'s'?></p></div><form method="post" action="/admin.php" class="actions"><input type="hidden" name="csrf" value="<?=html($csrf)?>"><input type="hidden" name="action" value="logout"><button class="btn secondary" type="submit">Sign out</button></form></header>
<section class="card"><h2>Completed reports</h2><div class="notice">Reports are stored on this server and visible only to this password-protected admin account. Delete each report when you no longer need it.</div><?php if (isset($_GET['deleted'])): ?><p class="muted" role="status">Report deleted.</p><?php endif; ?>
<?php if ($records === []): ?><div class="empty">No reports have been submitted yet.</div><?php else: ?><div class="table-wrap"><table><thead><tr><th>Completed</th><th>Participant</th><th>Email</th><th>Activities</th><th>Responses</th><th>Action</th></tr></thead><tbody>
<?php foreach ($records as $row): $participant = is_array($row['participant'] ?? null) ? $row['participant'] : []; $answers = is_array($row['answers'] ?? null) ? $row['answers'] : []; ?>
<tr><td><?=html((string)($row['submitted_at'] ?? ''))?></td><td><?=html((string)($participant['name'] ?? ''))?></td><td><a href="mailto:<?=html((string)($participant['email'] ?? ''))?>"><?=html((string)($participant['email'] ?? ''))?></a></td><td><?=count($answers)?> / 6<small>Completion only</small></td><td><details><summary>View answers</summary><div class="response"><?php foreach ($answers as $i => $answer): ?><?=html((string)($answer['activity'] ?? 'Activity'))?>
<?php if (isset($answer['criteria']) && is_array($answer['criteria'])): foreach ($answer['criteria'] as $criterion): ?> • <?=html((string)($criterion['criterion'] ?? ''))?>: <?=html((string)($criterion['judgment'] ?? ''))?> — <?=html((string)($criterion['explanation'] ?? ''))?>
<?php endforeach; else: ?> • <?=html((string)($answer['judgment'] ?? ''))?> — <?=html((string)($answer['explanation'] ?? ''))?>
<?php endif; ?>
<?php endforeach; ?></div></details></td><td><form method="post" action="/admin.php" onsubmit="return confirm('Permanently delete this report?')"><input type="hidden" name="csrf" value="<?=html($csrf)?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=html((string)($row['id'] ?? ''))?>"><button class="btn danger" type="submit">Delete</button></form></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php endif; ?></main></body></html>

<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/includes/bootstrap.php';

function read_hidden_password(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $canHide = DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec') && function_exists('stream_isatty') && stream_isatty(STDIN);
    if ($canHide) {
        shell_exec('stty -echo');
    }
    try {
        $value = fgets(STDIN);
    } finally {
        if ($canHide) {
            shell_exec('stty echo');
            fwrite(STDOUT, PHP_EOL);
        }
    }
    return trim((string)$value);
}

fwrite(STDOUT, "DataReadyTest admin setup\nCredentials are written outside the public website folder.\n\n");
fwrite(STDOUT, 'Admin username [dataadmin]: ');
$username = trim((string)fgets(STDIN));
if ($username === '') {
    $username = 'dataadmin';
}
if (!preg_match('/^[A-Za-z0-9_.@-]{3,80}$/', $username)) {
    fwrite(STDERR, "Invalid username. Use 3–80 letters, numbers, or . _ @ - characters.\n");
    exit(1);
}
$password = read_hidden_password('Admin password: ');
$confirmation = read_hidden_password('Confirm password: ');
if (strlen($password) < 12) {
    fwrite(STDERR, "Choose a password with at least 12 characters.\n");
    exit(1);
}
if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "The passwords do not match. Nothing was changed.\n");
    exit(1);
}
$config = ['username' => $username, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'created_at' => gmdate(DATE_ATOM)];
write_json_file('admin.json', $config);
fwrite(STDOUT, "Admin credentials saved in the private data directory. You can now sign in at /admin.php.\n");

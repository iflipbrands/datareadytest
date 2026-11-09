<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
send_security_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_response(405, ['ok' => false, 'error' => 'Method not allowed.']);
}
$contentType = strtolower(trim(explode(';', (string)($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    json_response(415, ['ok' => false, 'error' => 'Expected a JSON request.']);
}
$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 8192) {
    json_response(413, ['ok' => false, 'error' => 'The submitted summary is too large.']);
}
$input = json_decode($raw, true);
if (!is_array($input)
    || ($input['termsAccepted'] ?? false) !== true
    || !isset($input['participant'])
    || !is_array($input['participant'])
    || !is_int($input['completedActivities'] ?? null)
    || !is_int($input['totalActivities'] ?? null)
    || !is_int($input['durationSeconds'] ?? null)) {
    json_response(400, ['ok' => false, 'error' => 'The submitted summary is incomplete.']);
}
$name = trim((string)($input['participant']['name'] ?? ''));
$completed = $input['completedActivities'];
$total = $input['totalActivities'];
$duration = $input['durationSeconds'];
if ($name === '' || strlen($name) > 200) {
    json_response(400, ['ok' => false, 'error' => 'Please check the participant name.']);
}
if ($completed !== 6 || $total !== 6 || $duration < 0 || $duration > 43200) {
    json_response(400, ['ok' => false, 'error' => 'A complete six-activity summary with a valid elapsed time is required.']);
}
try {
    $record = [
        'id' => bin2hex(random_bytes(16)),
        'submitted_at' => gmdate(DATE_ATOM),
        'participant' => ['name' => $name],
        'terms_accepted' => true,
        'terms_accepted_at' => gmdate(DATE_ATOM),
        'completed_activities' => $completed,
        'total_activities' => $total,
        'score_type' => 'Completion only; not an accuracy score.',
        'duration_seconds' => $duration,
    ];
    update_json_file('results.json', static function (array $records) use ($record): array {
        array_unshift($records, $record);
        return $records;
    });
    json_response(201, ['ok' => true, 'message' => 'Your completion summary was saved.']);
} catch (Throwable $e) {
    error_log('DataReadyTest summary save failed: ' . $e->getMessage());
    json_response(500, ['ok' => false, 'error' => 'The summary could not be saved right now. Please try again later.']);
}

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
if ($raw === false || strlen($raw) > 65536) {
    json_response(413, ['ok' => false, 'error' => 'The submitted report is too large.']);
}
$input = json_decode($raw, true);
if (!is_array($input) || ($input['consent'] ?? false) !== true || !isset($input['participant'], $input['answers']) || !is_array($input['participant']) || !is_array($input['answers'])) {
    json_response(400, ['ok' => false, 'error' => 'The submitted report is incomplete.']);
}
$name = trim((string)($input['participant']['name'] ?? ''));
$email = trim((string)($input['participant']['email'] ?? ''));
if ($name === '' || strlen($name) > 200 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    json_response(400, ['ok' => false, 'error' => 'Please check the name and email in your report.']);
}
if (count($input['answers']) !== 6) {
    json_response(400, ['ok' => false, 'error' => 'A complete six-activity report is required.']);
}
$allowedCategories = ['Following Instructions', 'Visual Quality', 'Naturalness', 'Overall Preference'];
$allowedJudgments = ['A', 'B', 'Both Good', 'Both Bad'];
$answers = [];
foreach (array_values($input['answers']) as $answer) {
    if (!is_array($answer) || !isset($answer['activity'])) {
        json_response(400, ['ok' => false, 'error' => 'One of the activity responses is invalid.']);
    }
    $activity = trim((string)$answer['activity']);
    if ($activity === '' || strlen($activity) > 120) {
        json_response(400, ['ok' => false, 'error' => 'An activity name is invalid.']);
    }
    if (isset($answer['criteria'])) {
        if (!is_array($answer['criteria']) || count($answer['criteria']) !== 4) {
            json_response(400, ['ok' => false, 'error' => 'A calibration activity must contain four criteria.']);
        }
        $criteria = [];
        foreach (array_values($answer['criteria']) as $criterion) {
            if (!is_array($criterion) || !in_array($criterion['criterion'] ?? null, $allowedCategories, true) || !in_array($criterion['judgment'] ?? null, $allowedJudgments, true)) {
                json_response(400, ['ok' => false, 'error' => 'A calibration judgment is invalid.']);
            }
            $explanation = trim((string)($criterion['explanation'] ?? ''));
            if ($explanation === '' || text_length($explanation) > 250) {
                json_response(400, ['ok' => false, 'error' => 'Each judgment needs an explanation of no more than 250 characters.']);
            }
            $criteria[] = ['criterion' => $criterion['criterion'], 'judgment' => $criterion['judgment'], 'explanation' => $explanation];
        }
        $answers[] = ['activity' => $activity, 'criteria' => $criteria];
    } else {
        if (!in_array($answer['judgment'] ?? null, $allowedJudgments, true)) {
            json_response(400, ['ok' => false, 'error' => 'A practice judgment is invalid.']);
        }
        $explanation = trim((string)($answer['explanation'] ?? ''));
        if ($explanation === '' || text_length($explanation) > 250) {
            json_response(400, ['ok' => false, 'error' => 'Each judgment needs an explanation of no more than 250 characters.']);
        }
        $answers[] = ['activity' => $activity, 'judgment' => $answer['judgment'], 'explanation' => $explanation];
    }
}
try {
    $record = [
        'id' => bin2hex(random_bytes(16)),
        'submitted_at' => gmdate(DATE_ATOM),
        'site' => APP_NAME,
        'participant' => ['name' => $name, 'email' => $email],
        'consent' => true,
        'consented_at' => gmdate(DATE_ATOM),
        'completion' => '6 of 6 activities completed',
        'score_type' => 'Completion only; no accuracy score calculated.',
        'answers' => $answers,
    ];
    update_json_file('results.json', static function (array $records) use ($record): array {
        array_unshift($records, $record);
        return $records;
    });
    json_response(201, ['ok' => true, 'message' => 'Your results were saved for the site administrator.']);
} catch (Throwable $e) {
    error_log('DataReadyTest result save failed: ' . $e->getMessage());
    json_response(500, ['ok' => false, 'error' => 'The report could not be saved right now. Please try again later.']);
}

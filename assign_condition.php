<?php
declare(strict_types=1);

const ALLOWED_ORIGIN = 'https://bococo-81.inf.ed.ac.uk';
const MAX_BODY_BYTES = 64_000;
const ASSIGNMENT_DATA_DIR = '/home/bococo81/server_data/7BH/assignment';

header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!hash_equals(ALLOWED_ORIGIN, $origin)) {
    fail(403, 'Origin not allowed');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail(405, 'POST required');
}

$contentType = trim(strtolower($_SERVER['CONTENT_TYPE'] ?? ''));
if (!preg_match('/\Aapplication\/json(?:\s*;\s*charset=(?:utf-8|"utf-8"))?\z/D', $contentType)) {
    fail(415, 'Content-Type must be application/json');
}

$raw = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);
if ($raw === false || strlen($raw) > MAX_BODY_BYTES) {
    fail(413, 'Request body is too large');
}
try {
    $payload = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fail(400, 'Invalid JSON');
}

$sessionId = is_array($payload) ? ($payload['session_id'] ?? null) : null;
$participantId = is_array($payload) ? ($payload['participant_id'] ?? '') : '';
$version = is_array($payload) ? ($payload['experiment_version'] ?? 'unknown') : 'unknown';
if (!is_string($sessionId) || !preg_match('/\A[A-Za-z0-9_-]{8,80}\z/D', $sessionId)) {
    fail(400, 'Invalid session');
}
if (!is_string($participantId)
    || strlen($participantId) > 128
    || ($participantId !== '' && !preg_match('/\A[A-Za-z0-9_-]+\z/D', $participantId))) {
    fail(400, 'Invalid participant identifier');
}
if (!is_string($version) || strlen($version) > 120) {
    fail(400, 'Invalid experiment version');
}

$base = realpath(ASSIGNMENT_DATA_DIR);
if ($base === false || !is_dir($base) || is_link(ASSIGNMENT_DATA_DIR)) {
    fail(500, 'Assignment directory is unavailable');
}

$identity = $participantId !== '' ? 'participant:' . $participantId : 'session:' . $sessionId;
$identityHash = hash('sha256', $version . '|' . $identity);
$conditions = ['all-local', 'jump-2', 'jump-5'];
$path = $base . DIRECTORY_SEPARATOR . 'assignment-state.json';
if (is_link($path) || (file_exists($path) && !is_file($path))) {
    fail(400, 'Invalid assignment destination');
}

umask(0077);
$handle = fopen($path, 'c+');
if ($handle === false || !flock($handle, LOCK_EX)) {
    fail(500, 'Could not lock assignment state');
}

$contents = stream_get_contents($handle);
$state = json_decode($contents === false ? '' : $contents, true);
if (!is_array($state)) {
    $state = ['studies' => []];
}
if (!is_array($state['studies'] ?? null)) {
    $state['studies'] = [];
}
$studyKey = hash('sha256', $version);
if (!is_array($state['studies'][$studyKey] ?? null)) {
    $state['studies'][$studyKey] = [
        'queue' => [],
        'assignments' => [],
        'counts' => array_fill_keys($conditions, 0),
    ];
}
$study =& $state['studies'][$studyKey];
$study['queue'] = is_array($study['queue'] ?? null) ? $study['queue'] : [];
$study['assignments'] = is_array($study['assignments'] ?? null) ? $study['assignments'] : [];
$study['counts'] = is_array($study['counts'] ?? null)
    ? $study['counts'] : array_fill_keys($conditions, 0);

if (isset($study['assignments'][$identityHash])) {
    $condition = $study['assignments'][$identityHash]['condition'];
    $reused = true;
} else {
    if (count($study['queue']) === 0) {
        $study['queue'] = $conditions;
        for ($i = count($study['queue']) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$study['queue'][$i], $study['queue'][$j]] = [$study['queue'][$j], $study['queue'][$i]];
        }
    }
    $condition = array_shift($study['queue']);
    $study['assignments'][$identityHash] = [
        'condition' => $condition,
        'assigned_at' => gmdate('c'),
        'session_hash' => hash('sha256', $sessionId),
    ];
    $study['counts'][$condition] = (int)($study['counts'][$condition] ?? 0) + 1;
    $reused = false;
}

$encoded = json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded)) {
    flock($handle, LOCK_UN);
    fclose($handle);
    fail(500, 'Could not encode assignment state');
}
rewind($handle);
ftruncate($handle, 0);
if (fwrite($handle, $encoded . "\n") === false) {
    flock($handle, LOCK_UN);
    fclose($handle);
    fail(500, 'Could not save assignment state');
}
fflush($handle);
flock($handle, LOCK_UN);
fclose($handle);
chmod($path, 0600);

echo json_encode(['ok' => true, 'condition' => $condition, 'reused' => $reused]);

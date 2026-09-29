<?php
declare(strict_types=1);

const ALLOWED_ORIGIN = 'https://bococo-81.inf.ed.ac.uk';
const MAX_BODY_BYTES = 1_000_000;
const RESPONSE_DATA_DIR = '/home/bococo81/server_data/7BH/events';
const CONSENT_DATA_DIR = '/home/bococo81/server_data/7BH/consent';

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

function appendEvents(string $dataDir, string $sessionId, array $events): int
{
    if (count($events) === 0) {
        return 0;
    }

    $base = realpath($dataDir);
    if ($base === false || !is_dir($base) || is_link($dataDir)) {
        fail(500, 'Data directory is unavailable');
    }

    $path = $base . DIRECTORY_SEPARATOR . $sessionId . '.jsonl';
    if (is_link($path) || (file_exists($path) && !is_file($path))) {
        fail(400, 'Invalid destination');
    }

    umask(0077);
    $handle = fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        fail(500, 'Could not lock session record');
    }

    $known = [];
    rewind($handle);
    while (($line = fgets($handle)) !== false) {
        $row = json_decode($line, true);
        if (is_array($row) && is_string($row['event_id'] ?? null)) {
            $known[$row['event_id']] = true;
        }
    }

    $accepted = 0;
    fseek($handle, 0, SEEK_END);
    foreach ($events as $event) {
        $eventId = $event['event_id'];
        if (isset($known[$eventId])) {
            continue;
        }
        $encoded = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || fwrite($handle, $encoded . "\n") === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            fail(500, 'Could not save session record');
        }
        $known[$eventId] = true;
        $accepted += 1;
    }

    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    chmod($path, 0600);
    return $accepted;
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
    $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    fail(400, 'Invalid JSON');
}

$sessionId = is_array($payload) ? ($payload['session_id'] ?? null) : null;
$participantCode = is_array($payload) ? ($payload['participant_code'] ?? null) : null;
$events = is_array($payload) ? ($payload['events'] ?? null) : null;
if (!is_string($sessionId)
    || !preg_match('/\A[A-Za-z0-9_-]{8,80}\z/D', $sessionId)
    || $participantCode !== $sessionId
    || !is_array($events)
    || count($events) < 1
    || count($events) > 500) {
    fail(400, 'Invalid event payload');
}

$eventIdPattern = '/\A' . preg_quote($sessionId, '/') . '-[0-9]{6,}\z/D';
$consentEvents = [];
$responseEvents = [];
foreach ($events as $event) {
    if (!is_array($event)
        || ($event['session_id'] ?? null) !== $sessionId
        || ($event['participant_code'] ?? null) !== $participantCode
        || !is_int($event['event_index'] ?? null)
        || $event['event_index'] < 1
        || !is_string($event['event_id'] ?? null)
        || !preg_match($eventIdPattern, $event['event_id'])
        || !is_string($event['event_type'] ?? null)
        || strlen($event['event_type']) > 100
        || !is_string($event['stage'] ?? null)
        || strlen($event['stage']) > 40
        || !is_string($event['timestamp'] ?? null)
        || strlen($event['timestamp']) > 60) {
        fail(400, 'Invalid event record');
    }
    if ($event['stage'] === 'consent') {
        $consentEvents[] = $event;
    } else {
        $responseEvents[] = $event;
    }
}

$acceptedConsent = appendEvents(CONSENT_DATA_DIR, $sessionId, $consentEvents);
$acceptedResponses = appendEvents(RESPONSE_DATA_DIR, $sessionId, $responseEvents);

echo json_encode([
    'ok' => true,
    'accepted' => $acceptedConsent + $acceptedResponses,
    'received' => count($events),
    'consent_events' => count($consentEvents),
    'response_events' => count($responseEvents),
]);

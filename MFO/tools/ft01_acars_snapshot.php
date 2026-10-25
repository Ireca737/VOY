<?php
/**
 * VOYradar24 FT-01 — CLI-only, read-only API snapshot.
 * Eseguire dalla root public_html: php MFO/tools/ft01_acars_snapshot.php
 * Non esporre come endpoint web. Non scrive su database o file.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require_once dirname(__DIR__, 2) . '/proxy/api.php';
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/lib/functions.php';
\Proxy\Api\Api::__constructStatic();

$pilotId = $argv[1] ?? '14240';
$activityId = $argv[2] ?? '5280';
$endpoints = [
    'acars' => 'v1/map/acars',
    'bids' => 'v1/bids',
    'history' => 'v1/activity/history/' . rawurlencode($activityId) . '/' . rawurlencode($pilotId),
];
$out = [
    'captured_utc' => gmdate('c'),
    'pilot_id_filter' => $pilotId,
    'activity_id' => $activityId,
    'note' => 'I BID possono essere filtrati dalla sessione API; la portata globale non è dimostrata.',
    'responses' => [],
];
foreach ($endpoints as $key => $endpoint) {
    try {
        $response = \Proxy\Api\Api::sendAsync('GET', $endpoint, null);
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $decoded = json_decode($raw, true);
        $out['responses'][$key] = [
            'http_status' => $status,
            'body' => json_last_error() === JSON_ERROR_NONE ? $decoded : $raw,
        ];
    } catch (\Throwable $e) {
        $out['responses'][$key] = ['error' => get_class($e) . ': ' . $e->getMessage()];
    }
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

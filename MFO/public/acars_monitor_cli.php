<?php
/**
 * MFO FT-03 — ACARS monitor, CLI only.
 * Launch from public_html: php MFO/public/acars_monitor_cli.php
 * Runs until stopped by the operator; requires a persistent CLI process.
 * No HTTP access, no VaBase credits, no payouts.
 * Hosting process persistence MUST be validated before production.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
use Proxy\Api\Api;
require_once dirname(__DIR__, 2) . '/lib/functions.php';
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/proxy/api.php';
require_once dirname(__DIR__) . '/lib/acars_lifecycle.php';
require_once dirname(__DIR__) . '/lib/settlement_prepare.php';
require_once dirname(__DIR__, 2) . '/voy/secure_config/conn_voy.php';
if (!isset($conn) || !($conn instanceof mysqli)) throw new RuntimeException('Connessione VOY assente');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
Api::__constructStatic();

function mfo_monitor_fetch(string $endpoint): array {
    $response = Api::sendSync('GET', $endpoint, null);
    if ($response->getStatusCode() !== 200) throw new RuntimeException($endpoint . ' HTTP ' . $response->getStatusCode());
    $data = json_decode((string)$response->getBody(), true);
    if (!is_array($data) || !array_is_list($data)) throw new RuntimeException($endpoint . ' formato non valido');
    return $data;
}
function mfo_monitor_distance(int $legId): float {
    $response = Api::sendSync('GET', 'v1/activity/leg/' . $legId, null);
    if ($response->getStatusCode() !== 200) throw new RuntimeException('Leg VaBase non disponibile: ' . $legId);
    $leg = json_decode((string)$response->getBody(), true);
    $airports = $leg['airportInfo'] ?? [];
    if (!isset($airports[0]['lng'], $airports[0]['lat'], $airports[1]['lng'], $airports[1]['lat'])) {
        throw new RuntimeException('Coordinate aeroporti assenti per leg ' . $legId);
    }
    return (float)get_distance(
        (float)$airports[0]['lng'], (float)$airports[0]['lat'],
        (float)$airports[1]['lng'], (float)$airports[1]['lat']
    );
}
function mfo_monitor_tick(mysqli $conn): void {
    $bids = mfo_monitor_fetch('v1/bids');
    $acars = mfo_monitor_fetch('v1/map/acars');
    $pilots = [];
    foreach ($acars as $flight) {
        if (!is_array($flight)) continue;
        $pilotId = (int)($flight['pilotId'] ?? 0);
        if ($pilotId > 0) $pilots[$pilotId] = true;
    }
    foreach (array_keys($pilots) as $pilotId) {
        $events = mfo_reconcile_acars_lifecycle(
            $conn, (int)$pilotId, $bids, $acars,
            'mfo_monitor_distance',
            static function (int $programId) use ($conn): void {
                mfo_prepare_program_settlement($conn, $programId);
            }
        );
        foreach ($events as $event) {
            if (!empty($event['completed'])) {
                error_log('[MFO ACARS] COMPLETED leg=' . $event['activity_leg_id'] .
                    ' attempt=' . $event['actmpId'] . ' xp=' . $event['bonus_xp']);
            }
        }
    }
}
$lockName = 'voy_mfo_acars_monitor_v1';
$lockStmt = $conn->prepare('SELECT GET_LOCK(?, 0)');
$lockStmt->bind_param('s', $lockName);
$lockStmt->execute();
$lockStmt->bind_result($locked);
$lockStmt->fetch();
$lockStmt->close();
if ((int)$locked !== 1) throw new RuntimeException('Monitor MFO gia attivo');
try {
    while (true) {
        $started = microtime(true);
        try { mfo_monitor_tick($conn); }
        catch (Throwable $e) { error_log('[MFO ACARS] ' . $e->getMessage()); }
        $delay = max(0, 5.0 - (microtime(true) - $started));
        if ($delay > 0) usleep((int)($delay * 1000000));
    }
} finally {
    $release = $conn->prepare('SELECT RELEASE_LOCK(?)');
    $release->bind_param('s', $lockName);
    $release->execute();
    $release->close();
}

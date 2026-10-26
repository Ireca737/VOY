<?php
/**
 * VOY MFO - daily payout endpoint for cron-job.org.
 *
 * Deploy only after FT-03 approval. Default: DISABLED, no credits.
 * Temporary ?key= compatibility with VOY Suite; MFO_CRON_TOKEN overrides
 * the temporary key. MFO_PAYOUT_ENABLED=1 required for credits.
 * No ACARS polling here.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// Temporary compatibility with the existing VOY Suite cron URL.
// Replace the shared key before production; never log query strings.
$secret = getenv('MFO_CRON_TOKEN');
if (!is_string($secret) || $secret === '') {
    $secret = '!Ireca737!'; // TEMPORARY - rotate before production
}
$provided = $_GET['key'] ?? '';
if (!is_string($provided) || !hash_equals($secret, $provided)) {
    http_response_code(403);
    exit(json_encode(['ok'=>false,'error'=>'Forbidden']));
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit(json_encode(['ok'=>false,'error'=>'Method not allowed']));
}
if (getenv('MFO_PAYOUT_ENABLED') !== '1') {
    http_response_code(503);
    exit(json_encode(['ok'=>false,'mode'=>'DISABLED','error'=>'Accrediti MFO non abilitati']));
}

$lockHeld = false;
try {
    require_once __DIR__ . '/../../lib/functions.php';
    require_once __DIR__ . '/../../config.php';
    require_once __DIR__ . '/../../proxy/api.php';
    require_once __DIR__ . '/../../voy/secure_config/conn_voy.php';
    require_once __DIR__ . '/../lib/settlement_auto.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Connessione VOY assente');
    }
    $conn->query("SET time_zone = '+00:00'");
    $lock = $conn->query("SELECT GET_LOCK('voy_mfo_daily_payout',0) AS acquired")->fetch_assoc();
    if ((int)($lock['acquired'] ?? 0) !== 1) {
        http_response_code(409);
        exit(json_encode(['ok'=>false,'error'=>'Esecuzione MFO gia in corso']));
    }
    $lockHeld = true;

    if (session_status() !== PHP_SESSION_ACTIVE && !session_start()) {
        throw new RuntimeException('Sessione PHP richiesta dal wrapper VaBase non disponibile');
    }
    \Proxy\Api\Api::__constructStatic();
    $sender = static function (array $payload): array {
        $response = \Proxy\Api\Api::sendSync('POST', 'v1/pilot/credits/', $payload);
        return [
            'http_code' => (int)$response->getStatusCode(),
            'body' => (string)$response->getBody()
        ];
    };
    $out = mfo_run_daily_payouts($conn, $sender);
    // Do not expose pilot-level financial details through the cron response.
    $counts = ['SETTLED'=>0,'UNCERTAIN'=>0,'CHECK_REQUIRED'=>0,'OTHER'=>0];
    foreach ($out as $program) {
        if (($program['status'] ?? '') === 'CHECK_REQUIRED') $counts['CHECK_REQUIRED']++;
        foreach (($program['payments'] ?? []) as $payment) {
            $status = (string)($payment['status'] ?? 'OTHER');
            $key = array_key_exists($status, $counts) ? $status : 'OTHER';
            $counts[$key]++;
        }
    }
    echo json_encode(['ok'=>true,'utc'=>gmdate('c'),'programs_checked'=>count($out),
        'results'=>$counts], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[MFO cron payout] '.$e->getMessage());
    http_response_code(503);
    echo json_encode(['ok'=>false,'error'=>'Liquidazione MFO non riuscita; consultare error_log']);
} finally {
    if ($lockHeld) {
        try { $conn->query("SELECT RELEASE_LOCK('voy_mfo_daily_payout')"); }
        catch (Throwable $ignored) { error_log('[MFO cron payout] lock release failed'); }
    }
}

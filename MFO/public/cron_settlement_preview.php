<?php
/**
 * MFO daily cron — PREVIEW ONLY until explicitly enabled after FT-03.
 * Public URL for cron-job.org; temporary ?key= matching VOY Suite.
 * Does NOT query ACARS, mutate DB, or send XP/Cash to VaBase.
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
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../../voy/secure_config/conn_voy.php';
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Connessione VOY assente');
    }
    $sql = "SELECT p.id AS program_id, p.activity_id, p.status_mfo,
                   (SELECT COUNT(*) FROM voy_mfo_slots s WHERE s.activity_id=p.activity_id) AS total_slots,
                   (SELECT COUNT(*) FROM voy_mfo_slots s WHERE s.activity_id=p.activity_id
                    AND s.status_mfo='COMPLETED') AS completed_slots,
                   (SELECT COUNT(*) FROM voy_mfo_slots s WHERE s.activity_id=p.activity_id
                    AND s.status_mfo='OBSOLETE') AS obsolete_slots,
                   (SELECT COUNT(*) FROM voy_mfo_settlements t WHERE t.program_id=p.id
                    AND t.status='PENDING') AS pending_payments
            FROM voy_mfo_programs p
            WHERE p.status_mfo IN ('OPEN','READY')
            ORDER BY p.id";
    $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    $candidates = [];
    foreach ($rows as $row) {
        $total=(int)$row['total_slots'];
        $completed=(int)$row['completed_slots'];
        $obsolete=(int)$row['obsolete_slots'];
        if (($row['status_mfo']==='OPEN' && $total>0 && $completed>0
             && $total===$completed+$obsolete)
            || ($row['status_mfo']==='READY' && (int)$row['pending_payments']>0)) {
            $candidates[] = [
                'program_id'=>(int)$row['program_id'],
                'activity_id'=>(int)$row['activity_id'],
                'status'=>$row['status_mfo'],
                'completed'=>$completed,
                'obsolete'=>$obsolete,
                'pending_payments'=>(int)$row['pending_payments']
            ];
        }
    }
    echo json_encode(['ok'=>true,'mode'=>'PREVIEW_NO_CREDITS',
        'utc'=>gmdate('c'),'candidates'=>$candidates], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[MFO cron preview] '.$e->getMessage());
    http_response_code(503);
    echo json_encode(['ok'=>false,'error'=>'MFO preview non disponibile']);
}

<?php
/**
 * MFO FT-02 — controlled ACARS capture for one authenticated test pilot.
 * Test-only: requires the browser to remain open. NOT a production daemon.
 */
use Proxy\Api\Api;
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$pilotId = (int)($_SESSION['pilotid'] ?? 0);
if ($pilotId !== 14240) { http_response_code(403); exit('Pilota FT-02 non autorizzato'); }
if (empty($_SESSION['mfo_ft02_csrf'])) $_SESSION['mfo_ft02_csrf'] = bin2hex(random_bytes(24));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (!hash_equals($_SESSION['mfo_ft02_csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); exit(json_encode(['error'=>'CSRF']));
    }
    try {
        Api::__constructStatic();
        $resB = Api::sendSync('GET', 'v1/bids', null);
        $resA = Api::sendSync('GET', 'v1/map/acars', null);
        if ($resB->getStatusCode() !== 200 || $resA->getStatusCode() !== 200) throw new RuntimeException('API HTTP non 200');
        $bids = json_decode((string)$resB->getBody(), true);
        $acars = json_decode((string)$resA->getBody(), true);
        if (!is_array($bids) || !array_is_list($bids) || !is_array($acars) || !array_is_list($acars)) throw new RuntimeException('Formato API non valido');
        $matches = [];
        foreach ($acars as $flight) {
            if (!is_array($flight) || (int)($flight['pilotId'] ?? 0) !== $pilotId) continue;
            $attempt = (string)($flight['actmpId'] ?? '');
            $number = trim((string)($flight['flightNumber'] ?? ''));
            $stage = trim((string)($flight['statStage'] ?? ''));
            if ($attempt === '' || $number === '') continue;
            foreach ($bids as $bid) {
                if (!is_array($bid) || (int)($bid['pilotId'] ?? 0) !== $pilotId
                    || (string)($bid['flightNumber'] ?? '') !== $number
                    || (string)($bid['bidType'] ?? '') !== 'activity'
                    || (int)($bid['activityLegId'] ?? 0) < 1) continue;
                $matches[] = ['bid'=>(string)$bid['id'], 'leg'=>(int)$bid['activityLegId'],
                    'number'=>$number, 'attempt'=>$attempt, 'stage'=>$stage];
            }
        }
        require __DIR__ . '/voy/secure_config/conn_voy.php';
        if (!isset($conn) || !($conn instanceof mysqli)) throw new RuntimeException('Connessione VOY assente');
        $conn->query("SET time_zone = '+00:00'");
        $updated = [];
        foreach ($matches as $m) {
            // No reassignment of an existing different attempt. One-time completion.
            $sql = $m['stage'] === 'Disembarking'
                ? "UPDATE voy_mfo_slots SET actmp_id=?, status_flight=?, status_mfo='COMPLETED', completed_at=UTC_TIMESTAMP() WHERE activity_leg_id=? AND pilot_id=? AND bid_id=? AND status_mfo='BOOKED' AND completed_at IS NULL AND (actmp_id IS NULL OR actmp_id=?)"
                : "UPDATE voy_mfo_slots SET actmp_id=?, status_flight=? WHERE activity_leg_id=? AND pilot_id=? AND bid_id=? AND status_mfo='BOOKED' AND completed_at IS NULL AND (actmp_id IS NULL OR actmp_id=?)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) throw new RuntimeException('Prepare MFO fallita');
            $pid = (string)$pilotId;
            $stmt->bind_param('ssisss', $m['attempt'], $m['stage'], $m['leg'], $pid, $m['bid'], $m['attempt']);
            if (!$stmt->execute()) throw new RuntimeException('UPDATE MFO fallito');
            $updated[] = ['flight'=>$m['number'], 'stage'=>$m['stage'], 'actmpId'=>$m['attempt'], 'rows'=>$stmt->affected_rows];
            $stmt->close();
        }
        echo json_encode(['utc'=>gmdate('c'),'acars_count'=>count($acars),'matches'=>$updated], JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        error_log('[mfo_ft02] '.$e->getMessage());
        http_response_code(503); echo json_encode(['error'=>'Acquisizione FT-02 non riuscita; consultare error_log']);
    }
    exit;
}
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>MFO FT-02</title></head>
<body><h2>MFO FT-02 · ACARS test</h2><p>Solo pilota 14240. Tenere aperta questa pagina durante il volo. Campionamento ogni secondo. Non è un monitor permanente.</p>
<button id="toggle">Pausa</button><pre id="log"></pre>
<script>
const token=<?php echo json_encode($_SESSION['mfo_ft02_csrf']); ?>;
let running=true,busy=false;
document.getElementById('toggle').onclick=()=>{running=!running;document.getElementById('toggle').textContent=running?'Pausa':'Riprendi'};
async function poll(){
 if(!running||busy)return;busy=true;
 try{const body=new URLSearchParams({csrf:token});const r=await fetch(location.pathname,{method:'POST',credentials:'same-origin',body,cache:'no-store'});
 const data=await r.json();document.getElementById('log').textContent=JSON.stringify(data,null,2)+'\n'+document.getElementById('log').textContent.slice(0,9000);}
 catch(e){document.getElementById('log').textContent='ERRORE '+e.message+'\n'+document.getElementById('log').textContent;}
 finally{busy=false;}
}
poll();setInterval(poll,1000);
</script></body></html>

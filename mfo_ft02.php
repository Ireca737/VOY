<?php
/**
 * MFO FT-02 — controlled ACARS capture for one authenticated test pilot.
 * Test-only: requires the browser to remain open. NOT a production daemon.
 */
use Proxy\Api\Api;
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/config.php';
// FT-02 diagnostic mode: no lifecycle mutations.
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
        require __DIR__ . '/voy/secure_config/conn_voy.php';
        if (!isset($conn) || !($conn instanceof mysqli)) throw new RuntimeException('Connessione VOY assente');

        // Read-only snapshot. End Flight must not be inferred from BID disappearance.
        $stmt = $conn->prepare("SELECT slot_id, activity_id, activity_leg_id, pilot_id, bid_id, actmp_id, pirep_id, status_mfo, status_flight, booked_at, completed_at FROM voy_mfo_slots WHERE pilot_id=? ORDER BY slot_id DESC LIMIT 30");
        if (!$stmt) throw new RuntimeException('Query MFO FT-02 non disponibile');
        $pilotKey = (string)$pilotId;
        $stmt->bind_param('s', $pilotKey);
        $stmt->execute();
        $result = $stmt->get_result();
        $slots = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $myBids = array_values(array_filter($bids, static fn($bid) => is_array($bid) && (int)($bid['pilotId'] ?? 0) === $pilotId));
        $myAcars = array_values(array_filter($acars, static fn($flight) => is_array($flight) && (int)($flight['pilotId'] ?? 0) === $pilotId));
        $histories = [];
        foreach (array_unique(array_map('intval', array_column($slots, 'activity_id'))) as $activityId) {
            if ($activityId < 1) continue;
            try {
                $h = Api::sendSync('GET', 'v1/activity/history/' . $activityId . '/' . $pilotId, null);
                $body = (string)$h->getBody();
                $decoded = json_decode($body, true);
                $histories[(string)$activityId] = ['http'=>$h->getStatusCode(), 'data'=>json_last_error() === JSON_ERROR_NONE ? $decoded : ['invalid_json'=>true]];
            } catch (Throwable $historyError) {
                $histories[(string)$activityId] = ['error'=>'Richiesta storico non riuscita'];
                error_log('[mfo_ft02] History '.$activityId.': '.$historyError->getMessage());
            }
        }
        echo json_encode(['utc'=>gmdate('c'),'mode'=>'READ_ONLY','pilot_id'=>$pilotId,'slots'=>$slots,'bids'=>$myBids,'acars'=>$myAcars,'activity_history'=>$histories], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $e) {
        error_log('[mfo_ft02] '.$e->getMessage());
        http_response_code(503); echo json_encode(['error'=>'Acquisizione FT-02 non riuscita; consultare error_log']);
    }
    exit;
}
?><!doctype html><html lang="it"><head><meta charset="utf-8"><meta name="robots" content="noindex,nofollow"><title>MFO FT-02</title></head>
<body><h2>MFO FT-02 · Diagnostica End Flight</h2><p>Solo pilota 14240. Diagnostica di sola lettura: BID, ACARS, storico Activity e slot MFO. Non modifica gli stati. Campionamento ogni 15 secondi; non è un servizio permanente.</p>
<button id="toggle">Pausa</button><pre id="log"></pre>
<script>
const token=<?php echo json_encode($_SESSION['mfo_ft02_csrf']); ?>;
let running=true,busy=false;
document.getElementById('toggle').onclick=()=>{running=!running;document.getElementById('toggle').textContent=running?'Pausa':'Riprendi'};
async function poll(){
 if(!running||busy)return;busy=true;
 try{const body=new URLSearchParams({csrf:token});const r=await fetch(location.pathname,{method:'POST',credentials:'same-origin',body,cache:'no-store'});
 const data=await r.json();document.getElementById('log').textContent=JSON.stringify(data,null,2)+'\n'+document.getElementById('log').textContent.slice(0,18000);}
 catch(e){document.getElementById('log').textContent='ERRORE '+e.message+'\n'+document.getElementById('log').textContent;}
 finally{busy=false;}
}
poll();setInterval(poll,15000);
</script></body></html>

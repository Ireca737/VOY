<?php
/**
 * MFO FT-02 — controlled ACARS capture for one authenticated test pilot.
 * Test-only: requires the browser to remain open. NOT a production daemon.
 */
use Proxy\Api\Api;
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/MFO/lib/acars_lifecycle.php';
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

        $distanceResolver = static function (int $legId): float {
            $response = Api::sendSync('GET', 'v1/activity/leg/' . $legId, null);
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('Tratta VaBase non disponibile: ' . $legId);
            }
            $leg = json_decode((string)$response->getBody());
            if (!is_object($leg) || !isset($leg->airportInfo)
                || !is_array($leg->airportInfo) || count($leg->airportInfo) < 2) {
                throw new RuntimeException('Aeroporti VaBase mancanti: ' . $legId);
            }
            $coords = [];
            foreach ([$leg->airportInfo[0], $leg->airportInfo[1]] as $airport) {
                if (!is_object($airport) || !isset($airport->lng, $airport->lat)
                    || !is_numeric($airport->lng) || !is_numeric($airport->lat)) {
                    throw new RuntimeException('Coordinate VaBase non valide: ' . $legId);
                }
                $lng = (float)$airport->lng;
                $lat = (float)$airport->lat;
                if (!is_finite($lng) || !is_finite($lat)
                    || abs($lng) > 180 || abs($lat) > 90) {
                    throw new RuntimeException('Coordinate fuori intervallo: ' . $legId);
                }
                $coords[] = [$lng, $lat];
            }
            $distance = get_distance($coords[0][0], $coords[0][1], $coords[1][0], $coords[1][1]);
            if (!is_finite($distance) || $distance < 0) {
                throw new RuntimeException('Distanza VOY non calcolabile: ' . $legId);
            }
            return $distance;
        };
        $updated = mfo_reconcile_acars_lifecycle($conn, $pilotId, $bids, $acars, $distanceResolver);

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

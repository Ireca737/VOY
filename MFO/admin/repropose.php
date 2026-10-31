<?php
/**
 * MFO — Riproponi slot. VaBase owns Activity/Leg IDs.
 * Deploy: /public_html/admin/mfo/repropose.php
 */
require_once __DIR__ . '/_common.php';
use Proxy\Api\Api;

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405); exit('Metodo non consentito');
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || !mfo_program($conn, $id)) {
    http_response_code(404); exit('Programma MFO non trovato');
}
function mfo_repropose_activity(int $id): object {
    $response = Api::sendSync('GET', 'v1/activity/' . $id, null);
    if ($response->getStatusCode() !== 200) throw new RuntimeException('Activity VaBase non disponibile');
    $activity = json_decode((string)$response->getBody());
    if (!is_object($activity) || ($activity->type ?? null) !== 'Event' ||
        !isset($activity->activityLegs) || !is_array($activity->activityLegs)) {
        throw new RuntimeException('Activity Event o elenco tratte non valido');
    }
    return $activity;
}
function mfo_repropose_ids(object $activity): array {
    $ids = [];
    foreach ($activity->activityLegs as $leg) {
        $legId = filter_var($leg->id ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if (!$legId || isset($ids[$legId])) throw new RuntimeException('Identificativi Leg non validi o duplicati');
        $ids[$legId] = $leg;
    }
    return $ids;
}
function mfo_repropose_date($value): ?string {
    if ($value === null || $value === '') return null;
    // Preserve the original calendar minute; do not move expired dates.
    $value = str_replace(' ', 'T', (string)$value);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
        throw new RuntimeException('Formato data VaBase non riconosciuto');
    }
    return substr($value, 0, 16);
}
function mfo_repropose_slots(mysqli $conn, int $id): array {
    $stmt = $conn->prepare('SELECT activity_leg_id, status_mfo FROM voy_mfo_slots WHERE activity_id=?');
    if (!$stmt) throw new RuntimeException('Impossibile leggere gli slot originali');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Lettura slot originali fallita'); }
    $stmt->bind_result($legId, $status);
    $slots = [];
    while ($stmt->fetch()) {
        if (isset($slots[(int)$legId])) { $stmt->close(); throw new RuntimeException('Slot originale duplicato'); }
        $slots[(int)$legId] = $status;
    }
    $stmt->close();
    return $slots;
}
$error = null;
$source = null;
$selected = [];
$cashPrize = 0;
$block = !empty($_SESSION['mfo_repropose_block']);
try {
    $source = mfo_repropose_activity($id);
    $legs = mfo_repropose_ids($source);
    $slots = mfo_repropose_slots($conn, $id);
    // Every current VaBase Leg must have an MFO slot. Historical OBSOLETE
    // slots may legitimately remain in MFO after their VaBase Leg was deleted.
    if (array_diff_key($legs, $slots)) {
        throw new RuntimeException('Esistono tratte VaBase prive di slot MFO');
    }
    foreach ($slots as $legId => $status) {
        if (!isset($legs[$legId]) && $status !== 'OBSOLETE') {
            throw new RuntimeException('Slot MFO non obsoleto privo di tratta VaBase: Leg #' . $legId);
        }
    }
    foreach ($legs as $legId => $leg) {
        if ($slots[$legId] !== 'OBSOLETE') $selected[$legId] = $leg;
    }
    $stmt = $conn->prepare('SELECT cash_prize FROM voy_mfo_programs WHERE activity_id=? LIMIT 1');
    if (!$stmt) throw new RuntimeException('Montepremi sorgente non disponibile');
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) { $stmt->close(); throw new RuntimeException('Lettura montepremi fallita'); }
    $stmt->bind_result($cashPrize);
    if (!$stmt->fetch()) { $stmt->close(); throw new RuntimeException('Programma sorgente non trovato'); }
    $stmt->close();
    // Check format only, not whether dates are in the past.
    $start = mfo_repropose_date($source->startDate ?? null);
    $end = mfo_repropose_date($source->endDate ?? null);
    if ($start === null) throw new RuntimeException('Data iniziale originale assente');
    foreach ($selected as $leg) {
        if (!preg_match('/^[A-Z]{4}$/', strtoupper((string)($leg->departureIcao ?? ''))) ||
            !preg_match('/^[A-Z]{4}$/', strtoupper((string)($leg->arrivalIcao ?? '')))) {
            throw new RuntimeException('Tratta originale con ICAO non valido');
        }
    }
} catch (Throwable $e) {
    error_log('[MFO repropose] preflight source=' . $id . ' ' . $e->getMessage());
    $error = 'Impossibile preparare la riproposizione: ' . $e->getMessage();
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if ($block) $error = 'Riproposizione bloccata: verificare e riconciliare il precedente tentativo prima di ripetere.';
    elseif ($error === null && !mfo_verify()) $error = 'Token di sicurezza non valido. Ricaricare la pagina.';
    elseif ($error === null && (string)($_POST['confirm'] ?? '') !== '1') $error = 'Conferma richiesta.';
    if ($error === null) {
        $newId = null;
        $completed = 0;
        // PHP session locking serializes requests from the same session.
        // Keep the block until successful completion, including on ambiguous failures.
        $_SESSION['mfo_repropose_block'] = ['source' => $id, 'started' => gmdate('c')];
        unset($_SESSION['mfo_csrf']);
        try {
            $payload = [
                'Title' => (string)($source->title ?? ''),
                'Description' => (string)($source->description ?? ''),
                'Type' => 'Event',
                'Banner' => $source->banner ?? '',
                'AwardId' => $source->awardId ?? null,
                'BonusXp' => $source->bonusXp ?? null,
                'StartDate' => $start,
                'EndDate' => $end,
                'Active' => false,
                'LegsInOrder' => (bool)($source->legsInOrder ?? false),
                'MinRankId' => $source->minRankId ?? null,
            ];
            $response = Api::sendSync('POST', 'v1/activity', $payload);
            if ($response->getStatusCode() !== 200) throw new RuntimeException('Creazione Activity rifiutata: HTTP ' . $response->getStatusCode());
            $body = trim((string)$response->getBody(), " \t\n\r\0\x0B\" ");
            if (!ctype_digit($body) || (int)$body < 1) throw new RuntimeException('Nuovo Activity ID non verificabile');
            $newId = (int)$body;
            $insert = $conn->prepare('INSERT INTO voy_mfo_programs (activity_id, cash_prize) VALUES (?, ?)');
            if (!$insert) throw new RuntimeException('Registrazione programma MFO non disponibile');
            $prize = (int)$cashPrize;
            $insert->bind_param('ii', $newId, $prize);
            try { if (!$insert->execute()) throw new RuntimeException('Registrazione programma MFO fallita'); }
            finally { $insert->close(); }
            if (!mfo_program_is_open($conn, $newId)) throw new RuntimeException('Nuovo programma MFO non OPEN');
            $current = mfo_repropose_activity($newId);
            if (count(mfo_repropose_ids($current)) !== 0) throw new RuntimeException('Nuova Activity contiene già tratte');
            foreach ($selected as $leg) {
                if (!mfo_program_is_open($conn, $newId)) throw new RuntimeException('Programma non più OPEN');
                $before = mfo_repropose_ids($current);
                $legData = [
                    'ActivityId' => $newId,
                    'FlightNumber' => (string)($leg->flightNumber ?? ''),
                    'DepartureIcao' => strtoupper((string)$leg->departureIcao),
                    'ArrivalIcao' => strtoupper((string)$leg->arrivalIcao),
                    'Route' => (string)($leg->route ?? ''),
                    'Duration' => null,
                    'Description' => (string)($leg->description ?? ''),
                    'Aircraft' => (string)($leg->aircraft ?? ''),
                    'AircraftReg' => (string)($leg->aircraftReg ?? ''),
                ];
                $res = Api::sendSync('POST', 'v1/activity/leg', $legData);
                if ($res->getStatusCode() !== 200) throw new RuntimeException('Creazione Leg rifiutata: HTTP ' . $res->getStatusCode());
                $current = mfo_repropose_activity($newId);
                $after = mfo_repropose_ids($current);
                $added = array_diff_key($after, $before);
                if (count($added) !== 1 || count($after) !== count($before) + 1 ||
                    array_diff_key($before, $after)) throw new RuntimeException('Nuovo Leg ID ambiguo');
                $newLegId = (int)array_key_first($added);
                $candidate = $added[$newLegId];
                if (strtoupper((string)($candidate->departureIcao ?? '')) !== $legData['DepartureIcao'] ||
                    strtoupper((string)($candidate->arrivalIcao ?? '')) !== $legData['ArrivalIcao'] ||
                    strtoupper((string)($candidate->flightNumber ?? '')) !== strtoupper($legData['FlightNumber'])) {
                    throw new RuntimeException('Tratta VaBase creata non corrispondente');
                }
                $slot = $conn->prepare("INSERT INTO voy_mfo_slots (activity_id, activity_leg_id, status_mfo) VALUES (?, ?, 'AVAILABLE')");
                if (!$slot) throw new RuntimeException('Registrazione slot non disponibile');
                $slot->bind_param('ii', $newId, $newLegId);
                try { if (!$slot->execute()) throw new RuntimeException('Registrazione slot fallita'); }
                finally { $slot->close(); }
                $completed++;
            }
            unset($_SESSION['mfo_repropose_block']);
            header('Location: edit.php?id=' . $newId);
            exit;
        } catch (Throwable $e) {
            error_log('[MFO repropose] source=' . $id . ' new=' . ($newId ?? 'unknown') .
                ' legs_registered=' . $completed . ' error=' . $e->getMessage());
            $error = 'ATTENZIONE: riproposizione non completata. ' .
                ($newId ? 'Nuova Activity #' . $newId . '. ' : 'Nuovo Activity ID non verificabile. ') .
                $completed . ' tratte registrate prima dell’interruzione. ' .
                'Non ripetere: verificare Activity, Legs e registro MFO e riconciliare manualmente.';
        }
        $block = true;
    }
}
?>
<?php include __DIR__ . '/../includes/nav.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<main>
<header class="page-header page-header-compact page-header-light border-bottom bg-white mb-4">
<div class="container-fluid px-4"><div class="row align-items-center justify-content-between pt-3">
<div class="col-auto mb-3"><h1 class="page-header-title">Riproponi slot MFO</h1></div>
<div class="col-auto mb-3"><a class="btn btn-sm btn-light text-primary" href="index.php">Torna ai programmi</a></div>
</div></div></header>
<div class="container-fluid px-4">
<?php if ($error !== null): ?><div class="alert alert-danger" role="alert"><?=mfo_e($error)?></div><?php endif; ?>
<?php if ($block): ?>
<div class="alert alert-warning">È presente un tentativo di riproposizione da riconciliare. La sessione non consente nuovi tentativi. Non eliminare il blocco senza aver verificato i dati VaBase e MFO.</div>
<?php elseif ($error === null): ?>
<div class="card mb-4"><div class="card-header">Programma sorgente #<?=(int)$id?></div>
<div class="card-body">
<p><strong><?=mfo_e($source->title ?? '')?></strong></p>
<p>Tratte da riproporre: <strong><?=count($selected)?></strong> (escluse le OBSOLETE).</p>
<p>Le date originali saranno mantenute, anche se scadute. La nuova Activity sarà <strong>non attiva</strong>; potrai modificare date e altri campi nella schermata successiva.</p>
<form method="post" action="repropose.php?id=<?=(int)$id?>">
<input type="hidden" name="csrf_token" value="<?=mfo_e(mfo_token())?>">
<input type="hidden" name="confirm" value="1">
<button class="btn btn-primary" type="submit">Conferma riproposizione</button>
</form></div></div>
<?php endif; ?>
</div></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
use Proxy\Api\Api;
$MetaPageTitle = 'VOY MFO Flight Board';
$MetaPageDescription = 'Tratte dei programmi MFO Virtual Over Italy';
$MetaPageKeywords = '';
include 'lib/functions.php';
include 'config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
Api::__constructStatic();
function slot_e($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) { http_response_code(404); exit('Programma MFO non trovato'); }
try {
    // Keep VOY connection variables out of the VaBase page scope.
    $slotRows = (static function (int $activityId): array {
        require __DIR__ . '/voy/secure_config/conn_voy.php';
        if (!isset($conn) || !($conn instanceof mysqli)) throw new RuntimeException('Database VOY non disponibile');
        $check = $conn->prepare('SELECT id FROM voy_mfo_programs WHERE activity_id = ? LIMIT 1');
        if (!$check) throw new RuntimeException('Verifica programma non disponibile');
        $check->bind_param('i', $activityId);
        $check->execute();
        $check->store_result();
        $found = $check->num_rows === 1;
        $check->close();
        if (!$found) { http_response_code(404); exit('Programma MFO non trovato'); }
        $stmt = $conn->prepare("SELECT s.activity_leg_id, s.callsign, s.status_mfo, s.status_flight, p.pilota_nome FROM voy_mfo_slots s LEFT JOIN voy_suite_piloti p ON p.pilot_id = s.pilot_id WHERE s.activity_id = ? AND s.status_mfo <> 'OBSOLETE'");
        if (!$stmt) throw new RuntimeException('Elenco slot non disponibile');
        $stmt->bind_param('i', $activityId);
        $stmt->execute();
        $stmt->bind_result($legId, $callsign, $statusMfo, $statusFlight, $pilotName);
        $rows = [];
        while ($stmt->fetch()) $rows[(int)$legId] = ['callsign' => $callsign, 'pilot_name' => $pilotName, 'status_mfo' => $statusMfo, 'status_flight' => $statusFlight];
        $stmt->close();
        return $rows;
    })($id);
    $response = Api::sendSync('GET', 'v1/activity/' . $id, null);
    if ($response->getStatusCode() !== 200) throw new RuntimeException('Activity VaBase non disponibile');
    $activity = json_decode($response->getBody());
    if (!$activity || ($activity->type ?? '') !== 'Event') { http_response_code(404); exit('Programma MFO non valido'); }
} catch (Throwable $e) {
    if (http_response_code() === 404) exit('Programma MFO non trovato');
    error_log('[slot.php] ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(503);
    exit('Flight Board temporaneamente non disponibile');
}
$rows = [];
foreach (($activity->activityLegs ?? []) as $index => $leg) {
    $legId = (int)($leg->id ?? 0);
    if (!isset($slotRows[$legId])) continue;
    $info = $slotRows[$legId];
    $state = strtoupper(trim((string)$info['status_mfo']));
    // Only statuses defined by MFO are interpreted; unknown states remain visible.
    $priority = ['BOOKED' => 0, 'AVAILABLE' => 1, 'COMPLETED' => 2][$state] ?? 3;
    $rows[] = ['leg' => $leg, 'info' => $info, 'state' => $state, 'priority' => $priority, 'order' => $index];
}
usort($rows, static function ($a, $b) {
    return ($a['priority'] <=> $b['priority']) ?: ($a['order'] <=> $b['order']);
});
function slot_date($value): string {
    if (empty($value)) return '—';
    try { return (new DateTimeImmutable((string)$value, new DateTimeZone('UTC')))->format('d/m/Y'); }
    catch (Throwable $e) { return '—'; }
}
?>
<?php include 'includes/header.php'; ?>
<section id="content" class="cp section offset-header">
<div class="container">
  <p><a href="<?php echo slot_e(website_base_url); ?>slots.php"><i class="fa fa-angle-double-left" aria-hidden="true"></i> Programmi MFO</a></p>
  <div class="mfo-board">
    <div class="mfo-board-heading"><div><h2>DEPARTURES</h2><p><?php echo slot_e($activity->title ?? 'Programma MFO'); ?></p><div class="mfo-board-dates"><span>START <strong><?php echo slot_e(slot_date($activity->startDate ?? null)); ?></strong></span><span>END <strong><?php echo slot_e(slot_date($activity->endDate ?? null)); ?></strong></span><small>UTC</small></div></div><span class="mfo-board-label">VOY MFO</span></div>
    <div class="table-responsive">
      <table class="table mfo-board-table">
        <thead><tr><th>FLIGHT</th><th>FROM</th><th>TO</th><th>AIRCRAFT</th><th>STATUS</th><th>PILOT</th><th>ACTION</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row):
          $leg = $row['leg']; $state = $row['state']; $info = $row['info'];
          $statusClass = in_array($state, ['AVAILABLE', 'BOOKED', 'COMPLETED'], true) ? strtolower($state) : 'other';
        ?>
        <tr>
          <td class="mfo-flight"><?php echo slot_e($leg->flightNumber ?? '—'); ?></td>
          <td><?php echo slot_e($leg->departureIcao ?? '—'); ?></td>
          <td><?php echo slot_e($leg->arrivalIcao ?? '—'); ?></td>
          <td><?php echo slot_e($leg->aircraft ?? '—'); ?></td>
          <td><span class="mfo-status <?php echo slot_e($statusClass); ?>">● <?php echo slot_e(['AVAILABLE' => 'CHECK-IN OPEN', 'BOOKED' => 'BOARDING', 'COMPLETED' => 'DEPARTED'][$state] ?? $state); ?></span></td>
          <td><?php echo slot_e($info['pilot_name'] ?: ($info['callsign'] ?: '—')); ?></td>
          <td><?php if ($state === 'AVAILABLE'): ?><a class="btn btn-success btn-sm" href="<?php echo slot_e(website_base_url); ?>slot_leg.php?id=<?php echo (int)$leg->id; ?>">BOOK</a><?php else: ?>—<?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="text-center">Nessuna tratta MFO disponibile per questo programma.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</section>
<style>
.mfo-board{background:#101923;color:#eef5fb;border-radius:9px;padding:22px;margin:16px 0 35px}
.mfo-board-heading{display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:18px}
.mfo-board-heading h2{color:#fff;font-size:24px;margin:0 0 5px}
.mfo-board-heading p{color:#a9bacb;margin:0;text-transform:uppercase;letter-spacing:.6px}
.mfo-board-dates{display:flex;gap:18px;flex-wrap:wrap;margin-top:12px;color:#a9bacb;font-size:12px;letter-spacing:.7px}
.mfo-board-dates strong{color:#eef5fb;margin-left:6px;font-size:14px}
.mfo-board-dates small{color:#8299ac}
.mfo-board-label{background:#1f6648;color:#caffdd;border-radius:12px;padding:4px 12px;font-size:12px}
.mfo-board-table{color:#eef5fb;margin-bottom:0}
.mfo-board-table>thead>tr>th{color:#a9bacb;border-bottom:1px solid #3b4b5d;font-size:12px}
.mfo-board-table>tbody>tr>td{border-top:1px solid #293848;vertical-align:middle}
.mfo-board-table>tbody>tr:hover{background:#1d2b3b}
.mfo-flight{font-weight:700}
.mfo-status{white-space:nowrap;font-weight:600;font-size:12px}
.mfo-status.available{color:#59d49b}
.mfo-status.booked{color:#ffca70;animation:mfo-boarding-pulse 1.5s ease-in-out infinite}
@keyframes mfo-boarding-pulse{0%,100%{opacity:1}50%{opacity:.45}}
@media(prefers-reduced-motion:reduce){.mfo-status.booked{animation:none}}
.mfo-status.completed{color:#8cb6ff}
.mfo-status.other{color:#c2cbd4}
</style>
<?php include 'includes/footer.php'; ?>

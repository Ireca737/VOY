<?php
/**
 * VOY MFO - read-only settlement overview.
 * Server destination: /admin/mfo/settlement.php
 * No writes and no VaBase credit calls.
 */
require_once __DIR__ . '/_common.php';

$activityId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$activityId || !mfo_program($conn, (int)$activityId)) {
    http_response_code(404);
    exit('Programma MFO non trovato');
}

$stmt = $conn->prepare('SELECT id, activity_id, status_mfo, cash_prize, ready_at, settled_at FROM voy_mfo_programs WHERE activity_id=? LIMIT 1');
$stmt->bind_param('i', $activityId);
$stmt->execute();
$program = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT status_mfo, COUNT(*) AS n FROM voy_mfo_slots WHERE activity_id=? GROUP BY status_mfo");
$stmt->bind_param('i', $activityId);
$stmt->execute();
$slotCounts = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $slotCounts[$row['status_mfo']] = (int)$row['n'];
}
$stmt->close();

$stmt = $conn->prepare(
    'SELECT pilot_id, total_xp, cash_amount, status, reference_code, settled_at
     FROM voy_mfo_settlements WHERE program_id=? ORDER BY pilot_id'
);
$programId = (int)$program['id'];
$stmt->bind_param('i', $programId);
$stmt->execute();
$settlements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$slotTotal = array_sum($slotCounts);
$finished = ($slotCounts['COMPLETED'] ?? 0) + ($slotCounts['OBSOLETE'] ?? 0);
$canPrepare = $program['status_mfo'] === 'OPEN'
    && $slotTotal > 0 && $slotTotal === $finished
    && ($slotCounts['COMPLETED'] ?? 0) > 0;

?>
<?php include __DIR__.'/../includes/nav.php'; include __DIR__.'/../includes/sidebar.php'; ?>
<main>
<header class="page-header page-header-compact page-header-light border-bottom bg-white mb-4">
<div class="container-fluid px-4"><div class="row align-items-center pt-3">
<div class="col mb-3"><h1 class="page-header-title">Consuntivo MFO #<?= (int)$programId ?></h1></div>
<div class="col-auto"><a href="edit.php?id=<?= (int)$activityId ?>" class="btn btn-light">Torna al programma</a></div>
</div></div></header>
<div class="container-fluid px-4">
<div class="card mb-4"><div class="card-header">Stato programma</div><div class="card-body">
<p><strong>Activity VaBase:</strong> <?= (int)$activityId ?> |
<strong>Stato:</strong> <?= mfo_e($program['status_mfo']) ?> |
<strong>Montepremi:</strong> <?= number_format((int)$program['cash_prize'], 0, ',', '.') ?> Cash</p>
<p><strong>Slot:</strong> <?= $slotTotal ?> |
Completati: <?= (int)($slotCounts['COMPLETED'] ?? 0) ?> |
Obsoleti: <?= (int)($slotCounts['OBSOLETE'] ?? 0) ?> |
Altri: <?= $slotTotal - $finished ?></p>
<?php if ($program['status_mfo'] === 'OPEN'): ?>
<div class="alert alert-info"><?= $canPrepare
    ? 'Gli stati degli slot consentono la preparazione del consuntivo, previa verifica dei dati XP.'
    : 'Consuntivo non ancora preparabile: verificare gli slot e le tratte completate.' ?></div>
<p class="text-muted">Preparazione e liquidazione non sono abilitate in questa schermata di sola lettura.</p>
<?php endif; ?>
</div></div>
<div class="card mb-4"><div class="card-header">Liquidazioni registrate</div><div class="card-body">
<?php if (!$settlements): ?><p class="text-muted">Nessuna liquidazione preparata.</p>
<?php else: ?><div class="table-responsive"><table class="table table-bordered table-striped">
<thead><tr><th>Pilot ID</th><th>XP</th><th>Cash</th><th>Stato</th><th>Riferimento</th><th>Liquidato (UTC)</th></tr></thead><tbody>
<?php foreach ($settlements as $item): ?><tr>
<td><?= (int)$item['pilot_id'] ?></td>
<td><?= (int)$item['total_xp'] ?></td>
<td><?= (int)$item['cash_amount'] ?></td>
<td><?= mfo_e($item['status']) ?></td>
<td><?= mfo_e($item['reference_code']) ?></td>
<td><?= mfo_e($item['settled_at'] ?? '—') ?></td>
</tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
</div></div>
</div></main>
<?php include __DIR__.'/../includes/footer.php'; ?>

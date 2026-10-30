<?php
require_once __DIR__ . '/_common.php';

$legId = filter_input(INPUT_GET, 'leg_id', FILTER_VALIDATE_INT);
if (!$legId || $legId < 1) {
    http_response_code(400);
    exit('Leg ID non valido');
}

$stmt = $conn->prepare('SELECT activity_id, status_mfo FROM voy_mfo_slots WHERE activity_leg_id=? LIMIT 1');
if (!$stmt) {
    http_response_code(500);
    exit('Registro MFO non disponibile');
}
$stmt->bind_param('i', $legId);
$stmt->execute();
$stmt->bind_result($activityId, $status);
$found = $stmt->fetch();
$stmt->close();

if (!$found || !mfo_program($conn, (int)$activityId)) {
    http_response_code(404);
    exit('Tratta MFO non registrata');
}

$activityId = (int)$activityId;
$error = null;
$completed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!mfo_verify()) {
        $error = 'Token di sicurezza non valido';
    } elseif (!mfo_program_is_open($conn, $activityId)) {
        $error = 'Programma non OPEN: annullamento vietato';
    } elseif ((string)($_POST['confirm'] ?? '') !== 'DELETE') {
        $error = 'Conferma non valida';
    } elseif ($status !== 'AVAILABLE') {
        $error = 'Solo le tratte AVAILABLE possono essere annullate';
    } else {
        try {
            // Transizione atomica: nessuna modifica a tratte prenotate o concluse.
            // Nessuna chiamata API: la tratta rimane nel catalogo VaBase.
            $update = $conn->prepare(
                "UPDATE voy_mfo_slots SET status_mfo='OBSOLETE' " .
                "WHERE activity_id=? AND activity_leg_id=? AND status_mfo='AVAILABLE'"
            );
            if (!$update) {
                throw new RuntimeException('Aggiornamento MFO non disponibile');
            }
            $update->bind_param('ii', $activityId, $legId);
            try {
                if (!$update->execute()) {
                    throw new RuntimeException('Aggiornamento MFO fallito');
                }
                $affected = $update->affected_rows;
            } finally {
                $update->close();
            }
            if ($affected !== 1) {
                $error = 'Tratta non più AVAILABLE: annullamento non eseguito';
            } else {
                $completed = true;
                $status = 'OBSOLETE';
            }
        } catch (Throwable $e) {
            error_log('[MFO obsolete] activity=' . $activityId . ' leg=' . $legId . ' error=' . get_class($e) . ' ' . $e->getMessage());
            $error = 'Annullamento non completato. Verificare il registro MFO prima di riprovare.';
        }
    }
}
?>
<?php include __DIR__ . '/../includes/nav.php'; include __DIR__ . '/../includes/sidebar.php'; ?>
<main><div class="container-fluid px-4 py-4"><h1>Annulla tratta MFO #<?= (int)$legId ?></h1>
<?php if ($error): ?><div class="alert alert-danger"><?= mfo_e($error) ?></div><?php endif; ?>
<?php if ($completed): ?><div class="alert alert-success">Tratta contrassegnata OBSOLETE nel registro MFO. La tratta rimane nel catalogo VaBase.</div><?php endif; ?>
<p>Programma #<?= (int)$activityId ?> — stato VOY: <?= mfo_e($status) ?></p>
<?php if (!$completed && $status === 'AVAILABLE' && mfo_program_is_open($conn, $activityId)): ?>
<div class="alert alert-warning">L'annullamento rende la tratta definitivamente OBSOLETE in MFO. Il record viene conservato e la tratta non viene cancellata da VaBase.</div>
<form method="post"><input type="hidden" name="csrf_token" value="<?= mfo_e(mfo_token()) ?>"><input type="hidden" name="confirm" value="DELETE">
<button class="btn btn-danger" type="submit" onclick="return confirm('Confermi l’annullamento definitivo della tratta MFO #<?= (int)$legId ?>?')">Conferma annullamento</button></form>
<?php endif; ?>
<a class="btn btn-light mt-3" href="edit.php?id=<?= (int)$activityId ?>">Torna al programma</a>
</div></main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

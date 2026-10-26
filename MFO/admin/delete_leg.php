<?php
require_once __DIR__ . '/_common.php';
$legId=filter_input(INPUT_GET,'leg_id',FILTER_VALIDATE_INT);
if (!$legId || $legId<1) { http_response_code(400); exit('Leg ID non valido'); }
$stmt=$conn->prepare('SELECT activity_id, status_mfo FROM voy_mfo_slots WHERE activity_leg_id=? LIMIT 1');
if (!$stmt) { http_response_code(500); exit('Registro MFO non disponibile'); }
$stmt->bind_param('i',$legId); $stmt->execute(); $stmt->bind_result($activityId,$status);
$found=$stmt->fetch(); $stmt->close();
if (!$found || !mfo_program($conn,(int)$activityId)) { http_response_code(404); exit('Tratta MFO non registrata'); }
$activityId=(int)$activityId;
$error=null;
$completed=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!mfo_verify()) $error='Token di sicurezza non valido';
 elseif (!mfo_program_is_open($conn,$activityId)) $error='Programma non OPEN: eliminazione vietata';
 elseif ((string)($_POST['confirm']??'')!=='DELETE') $error='Conferma non valida';
 elseif ($status==='OBSOLETE') $error='Tratta già contrassegnata OBSOLETE';
 else {
  try {
   $remote=\Proxy\Api\Api::sendSync('GET','v1/activity/'.$activityId,null);
   if ($remote->getStatusCode()!==200) throw new RuntimeException('Activity non disponibile');
   $activity=json_decode((string)$remote->getBody());
   if (!$activity || !isset($activity->activityLegs) || !is_array($activity->activityLegs)) throw new RuntimeException('Elenco tratte non disponibile');
   $present=false;
   foreach ($activity->activityLegs as $leg) if ((int)($leg->id??0)===$legId) $present=true;
   if ($present) {
    $res=\Proxy\Api\Api::sendSync('DELETE','v1/activity/leg/'.$legId,null);
    // The API may return a success status with an empty response body.
    if (!in_array($res->getStatusCode(),[200,204],true)) throw new RuntimeException('DELETE VaBase non confermata');
   }
   // Re-read the parent Activity; only a confirmed absence allows VOY to be updated.
   $check=\Proxy\Api\Api::sendSync('GET','v1/activity/'.$activityId,null);
   if ($check->getStatusCode()!==200) throw new RuntimeException('Verifica VaBase non disponibile');
   $after=json_decode((string)$check->getBody());
   if (!$after || !isset($after->activityLegs) || !is_array($after->activityLegs)) throw new RuntimeException('Elenco tratte non verificabile');
   foreach ($after->activityLegs as $leg) {
    if ((int)($leg->id??0)===$legId) throw new RuntimeException('La tratta risulta ancora presente su VaBase');
   }
   $update=$conn->prepare("UPDATE voy_mfo_slots SET status_mfo='OBSOLETE' WHERE activity_id=? AND activity_leg_id=? AND status_mfo<>'OBSOLETE'");
   if (!$update) throw new RuntimeException('Aggiornamento VOY non disponibile');
   $update->bind_param('ii',$activityId,$legId);
   if (!$update->execute()) { $update->close(); throw new RuntimeException('Aggiornamento VOY fallito'); }
   $affected=$update->affected_rows; $update->close();
   if ($affected!==1) throw new RuntimeException('Registro VOY non aggiornato: verificare lo stato');
   $completed=true;
  } catch (Throwable $e) {
   error_log('[MFO delete] activity='.$activityId.' leg='.$legId.' error='.get_class($e).' '.$e->getMessage());
   $error='Operazione non completata. Verificare VaBase e registro MFO prima di riprovare.';
  }
 }
}
?>
<?php include __DIR__.'/../includes/nav.php'; include __DIR__.'/../includes/sidebar.php'; ?>
<main><div class="container-fluid px-4 py-4"><h1>Elimina tratta MFO #<?= (int)$legId ?></h1>
<?php if($error): ?><div class="alert alert-danger"><?=mfo_e($error)?></div><?php endif; ?>
<?php if($completed): ?><div class="alert alert-success">Tratta eliminata da VaBase e contrassegnata OBSOLETE in VOY.</div><?php endif; ?>
<p>Programma #<?= (int)$activityId ?> — stato VOY: <?=mfo_e($completed?'OBSOLETE':$status)?></p>
<?php if(!$completed && $status!=='OBSOLETE' && mfo_program_is_open($conn,$activityId)): ?>
<div class="alert alert-warning">L'eliminazione rimuove la tratta da VaBase. Il record VOY viene conservato con stato OBSOLETE.</div>
<form method="post"><input type="hidden" name="csrf_token" value="<?=mfo_e(mfo_token())?>"><input type="hidden" name="confirm" value="DELETE">
<button class="btn btn-danger" type="submit" onclick="return confirm('Confermi la cancellazione definitiva della tratta #<?= (int)$legId ?> da VaBase?')">Conferma eliminazione</button></form>
<?php endif; ?>
<a class="btn btn-light mt-3" href="edit.php?id=<?=(int)$activityId?>">Torna al programma</a>
</div></main>
<?php include __DIR__.'/../includes/footer.php'; ?>

<?php
require_once __DIR__.'/_common.php';
$legId=filter_input(INPUT_GET,'leg_id',FILTER_VALIDATE_INT);
if (!$legId) { http_response_code(400); exit('Leg ID non valido'); }
$leg=mfo_leg_activity($legId);
if (!$leg || empty($leg->activityId) || !mfo_program($conn,(int)$leg->activityId)) {
 http_response_code(404); exit('Tratta MFO non trovata');
}
$id=(int)$leg->activityId;
$stmt=$conn->prepare('SELECT status_mfo FROM voy_mfo_slots WHERE activity_leg_id=? AND activity_id=? LIMIT 1');
if (!$stmt) { http_response_code(500); exit('Registro slot non disponibile'); }
$stmt->bind_param('ii',$legId,$id); $stmt->execute(); $stmt->bind_result($slotStatus);
$found=$stmt->fetch(); $stmt->close();
if (!$found) { http_response_code(409); exit('Slot non registrato: riconciliare prima di modificare'); }
$editable=$slotStatus==='AVAILABLE';
$error=null; $success=false;
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!$editable) $error='Slot non disponibile: modifica vietata';
 elseif (!mfo_verify()) $error='Token non valido';
 else {
  $dep=strtoupper(trim((string)($_POST['departure']??'')));
  $arr=strtoupper(trim((string)($_POST['arrival']??'')));
  $desc=(string)($_POST['description']??''); $doc=json_decode($desc);
  if (!preg_match('/^[A-Z]{4}$/',$dep) || !preg_match('/^[A-Z]{4}$/',$arr) ||
      !isset($doc->blocks) || !is_array($doc->blocks) || !$doc->blocks) $error='ICAO o descrizione EditorJS non validi';
  else {
   $data=['Id'=>$legId,'ActivityId'=>$id,'FlightNumber'=>strtoupper(trim((string)($_POST['flightNumber']??''))),
    'DepartureIcao'=>$dep,'ArrivalIcao'=>$arr,'Route'=>strtoupper(trim((string)($_POST['route']??''))),
    'Duration'=>$leg->duration??null,'Description'=>$desc,'Aircraft'=>strtoupper(trim((string)($_POST['aircraft']??''))),
    'AircraftReg'=>strtoupper(trim((string)($_POST['aircraftReg']??'')))];
   $res=\Proxy\Api\Api::sendSync('PUT','v1/activity/leg',$data);
   if ($res->getStatusCode()===200) { $success=true; $leg=mfo_leg_activity($legId)??$leg; }
   else $error='VaBase non ha accettato la modifica';
  }
 }
}
?>
<?php include __DIR__.'/../includes/nav.php'; include __DIR__.'/../includes/sidebar.php'; ?>
<main><header class="page-header page-header-compact page-header-light border-bottom bg-white mb-4"><div class="container-fluid px-4"><div class="row pt-3"><div class="col mb-3"><h1 class="page-header-title">Modifica tratta MFO #<?=(int)$legId?></h1></div><div class="col-auto"><a class="btn btn-light" href="edit.php?id=<?=$id?>">Torna al programma</a></div></div></div></header>
<div class="container-fluid px-4">
<?php if($error): ?><div class="alert alert-danger"><?=mfo_e($error)?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success">Tratta aggiornata.</div><?php endif; ?>
<?php if(!$editable): ?><div class="alert alert-warning">Slot in stato <?=mfo_e($slotStatus)?>. Modifiche disabilitate per proteggere prenotazioni e voli.</div><?php endif; ?>
<div class="card mb-4"><div class="card-body"><form method="post"><input type="hidden" name="csrf_token" value="<?=mfo_e(mfo_token())?>">
<div class="row"><div class="col-md-6 mb-3"><label>Partenza ICAO</label><input class="form-control" name="departure" maxlength="4" required value="<?=mfo_e($leg->departureIcao??'')?>" <?=$editable?'':'disabled'?>></div>
<div class="col-md-6 mb-3"><label>Arrivo ICAO</label><input class="form-control" name="arrival" maxlength="4" required value="<?=mfo_e($leg->arrivalIcao??'')?>" <?=$editable?'':'disabled'?>></div></div>
<div class="row"><div class="col-md-6 mb-3"><label>Numero volo</label><input class="form-control" name="flightNumber" value="<?=mfo_e($leg->flightNumber??'')?>" <?=$editable?'':'disabled'?>></div><div class="col-md-6 mb-3"><label>Aeromobile ICAO</label><input class="form-control" name="aircraft" value="<?=mfo_e($leg->aircraft??'')?>" <?=$editable?'':'disabled'?>></div></div>
<div class="mb-3"><label>Rotta</label><input class="form-control" name="route" value="<?=mfo_e($leg->route??'')?>" <?=$editable?'':'disabled'?>></div>
<div class="mb-3"><label>Registrazione aeromobile</label><input class="form-control" name="aircraftReg" value="<?=mfo_e($leg->aircraftReg??'')?>" <?=$editable?'':'disabled'?>></div>
<div class="mb-3"><label class="form-label">Descrizione</label><div id="editorJs" class="form-control" style="min-height:180px"></div><input type="hidden" name="description" id="mfoDescription" value="<?=mfo_e($leg->description??'')?>"></div>
<?php if($editable): ?><button class="btn btn-primary">Salva tratta</button><?php endif; ?>
</form></div></div></div></main>
<?php include __DIR__.'/../includes/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/header@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/list@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/embed@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/raw@latest"></script>
<script>
(function () {
  const holder = document.getElementById('editorJs');
  const field = document.getElementById('mfoDescription');
  const form = field && field.closest('form');
  if (!holder || !field || !form) return;
  if (typeof EditorJS === 'undefined' || typeof Header === 'undefined' || typeof EditorjsList === 'undefined' || typeof Embed === 'undefined' || typeof RawTool === 'undefined') { holder.textContent = 'Editor non disponibile. Ricaricare la pagina.'; form.querySelector('[type=submit]').disabled = true; return; }
  let data;
  try { data = field.value ? JSON.parse(field.value) : undefined; } catch (_) { data = undefined; }
  const editor = new EditorJS({holder: 'editorJs', data, readOnly: false, tools: {
    header: {class: Header, inlineToolbar: true},
    list: {class: EditorjsList, inlineToolbar: true},
    embed: Embed,
    raw: RawTool
  }});
  <?php if (!$editable): ?>
  holder.textContent = 'Slot non modificabile. Descrizione conservata nel registro VaBase.';
  return;
  <?php endif; ?>
  let sending = false;
  form.addEventListener('submit', async function (event) {
    if (sending) return;
    event.preventDefault();
    try {
      await editor.isReady;
      const result = await editor.save();
      if (!result.blocks || !result.blocks.length) { alert('Inserire una descrizione.'); return; }
      field.value = JSON.stringify(result);
      sending = true;
      form.requestSubmit();
    } catch (error) { alert('Impossibile salvare la descrizione: ' + error.message); }
  });
})();
</script>

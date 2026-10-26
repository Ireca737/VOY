<?php
require_once __DIR__ . '/_common.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if (!$id || !mfo_program($conn,$id)) { http_response_code(404); exit('Programma MFO non trovato'); }
$error=null; $success=false;
$programOpen=mfo_program_is_open($conn,$id);
$cashRow=$conn->query('SELECT cash_prize FROM voy_mfo_programs WHERE activity_id='.(int)$id)->fetch_assoc();
$cashPrize=(int)($cashRow['cash_prize']??0);
$r=\Proxy\Api\Api::sendSync('GET','v1/activity/'.$id,null);
if ($r->getStatusCode()!==200) { http_response_code(502); exit('Activity VaBase non disponibile'); }
$a=json_decode($r->getBody());
if (!$a || $a->type!=='Event') { http_response_code(409); exit('Activity MFO non valida'); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (!$programOpen) $error='Programma MFO non OPEN: modifica vietata';
  elseif (!mfo_verify()) $error='Token di sicurezza non valido';
  else {
    $cashInput=trim((string)($_POST['cash_prize']??''));
    $title=trim((string)($_POST['title']??''));
    $description=(string)($_POST['description']??'');
    $doc=json_decode($description);
    $start=(string)($_POST['start']??''); $end=(string)($_POST['end']??'');
    $ds=DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i',$start,new DateTimeZone('UTC'));
    $de=$end!==''?DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i',$end,new DateTimeZone('UTC')):null;
    if (!ctype_digit($cashInput) || (float)$cashInput>2147483647) $error='Montepremi Cash non valido: inserire un intero non negativo';
    elseif ($title==='' || !isset($doc->blocks) || !is_array($doc->blocks) || !$doc->blocks || !$ds ||
        $ds->format('Y-m-d\\TH:i')!==$start || ($end!=='' && (!$de || $de->format('Y-m-d\\TH:i')!==$end)) ||
        ($de && $de<=$ds)) $error='Controllare titolo, descrizione EditorJS e date UTC';
    else {
      $data=[
        'Id'=>$id,'Title'=>$title,'Description'=>$description,'Type'=>'Event',
        'Banner'=>$a->banner??'', 'AwardId'=>$a->awardId??null,
        'BonusXp'=>null,'StartDate'=>$start,'EndDate'=>$end?:null,
        'Active'=>isset($_POST['active']), 'LegsInOrder'=>false,'MinRankId'=>$a->minRankId??null
      ];
      $res=\Proxy\Api\Api::sendSync('PUT','v1/activity',$data);
      if ($res->getStatusCode()===200) {
        $newCash=(int)$cashInput;
        $stmt=$conn->prepare("UPDATE voy_mfo_programs SET cash_prize=? WHERE activity_id=? AND status_mfo='OPEN'");
        $stmt->bind_param('ii',$newCash,$id);
        if (!$stmt->execute()) $error='Activity aggiornata in VaBase, ma montepremi Cash non salvato: verificare manualmente';
        else { $cashPrize=$newCash; $success=true; }
        $stmt->close();
        $r=\Proxy\Api\Api::sendSync('GET','v1/activity/'.$id,null); if ($r->getStatusCode()===200) $a=json_decode($r->getBody()); }
      else $error='VaBase non ha accettato la modifica';
    }
  }
}
$startValue=isset($a->startDate)?substr(str_replace(' ','T',$a->startDate),0,16):'';
$endValue=!empty($a->endDate)?substr(str_replace(' ','T',$a->endDate),0,16):'';
?>
<?php include __DIR__.'/../includes/nav.php'; include __DIR__.'/../includes/sidebar.php'; ?>
<main><header class="page-header page-header-compact page-header-light border-bottom bg-white mb-4"><div class="container-fluid px-4"><div class="row align-items-center pt-3"><div class="col mb-3"><h1 class="page-header-title">Modifica MFO #<?= (int)$id ?></h1></div><div class="col-auto"><a href="index.php" class="btn btn-light">Programmi MFO</a></div></div></div></header>
<div class="container-fluid px-4">
<?php if($error): ?><div class="alert alert-danger"><?=mfo_e($error)?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success">Modifiche salvate in VaBase.</div><?php endif; ?>
<div class="card mb-4"><div class="card-header">Dati programma</div><div class="card-body">
<form method="post"><fieldset <?= $programOpen ? '' : 'disabled' ?>><input type="hidden" name="csrf_token" value="<?=mfo_e(mfo_token())?>">
<div class="mb-3"><label class="form-label">Titolo</label><input class="form-control" name="title" required value="<?=mfo_e($a->title??'')?>"></div>
<div class="mb-3"><label class="form-label">Descrizione</label><div id="editorJs" class="form-control" style="min-height:180px"></div><input type="hidden" name="description" id="mfoDescription" value="<?=mfo_e($a->description??'')?>"></div>
<div class="row"><div class="col-md-6 mb-3"><label class="form-label">Inizio UTC</label><input type="datetime-local" class="form-control" name="start" required value="<?=mfo_e($startValue)?>"></div>
<div class="col-md-6 mb-3"><label class="form-label">Fine UTC</label><input type="datetime-local" class="form-control" name="end" value="<?=mfo_e($endValue)?>"></div></div>
<div class="mb-3"><label class="form-label" for="cash_prize">Montepremi Cash totale</label><input class="form-control" type="number" min="0" max="2147483647" step="1" id="cash_prize" name="cash_prize" required value="<?= (int)$cashPrize ?>"><div class="form-text">Valore complessivo del premio; 0 se non previsto.</div></div>
<div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="active" id="active" <?=filter_var($a->active??false,FILTER_VALIDATE_BOOLEAN)?'checked':''?>><label for="active" class="form-check-label">Attivo</label></div>
<p class="text-muted">Tipo Event e LegsInOrder=false sono fissi. Il bonus XP MFO viene gestito separatamente.</p>
<?php if($programOpen): ?><button class="btn btn-primary">Salva</button><?php else: ?><div class="alert alert-warning">Programma chiuso: modifiche disabilitate.</div><?php endif; ?></fieldset></form></div></div>
<div class="card mb-4"><div class="card-header">Slot / tratte <?php if($programOpen): ?><a class="btn btn-sm btn-primary float-end" href="create_leg.php?id=<?=(int)$id?>">Nuova tratta</a><?php endif; ?></div>
<div class="card-body"><table class="table table-bordered"><thead><tr><th>Leg ID</th><th>Partenza</th><th>Arrivo</th><th>Volo</th><th>Azioni</th></tr></thead><tbody>
<?php foreach(($a->activityLegs??[]) as $leg): ?><tr><td><?= (int)$leg->id ?></td><td><?=mfo_e($leg->departureIcao??'')?></td><td><?=mfo_e($leg->arrivalIcao??'')?></td><td><?=mfo_e($leg->flightNumber??'')?></td><td><a href="edit_leg.php?leg_id=<?=(int)$leg->id?>">Modifica</a> <a class="text-danger ms-2" href="delete_leg.php?leg_id=<?=(int)$leg->id?>">Elimina</a></td></tr><?php endforeach; ?>
</tbody></table></div></div></div></main>
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
  const editor = new EditorJS({holder: 'editorJs', data, tools: {
    header: {class: Header, inlineToolbar: true},
    list: {class: EditorjsList, inlineToolbar: true},
    embed: Embed,
    raw: RawTool
  }});
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

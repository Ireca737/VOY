<?php
require_once __DIR__.'/_common.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if (!$id || !mfo_program($conn,$id)) { http_response_code(404); exit('Programma MFO non trovato'); }
$r=\Proxy\Api\Api::sendSync('GET','v1/activity/'.$id,null);
if ($r->getStatusCode()!==200) { http_response_code(502); exit('Activity VaBase non disponibile'); }
$a=json_decode($r->getBody());
if (!$a || $a->type!=='Event') { http_response_code(409); exit('Activity non valida'); }
$error=null;
// Administrative reconciliation of a prior uncertain POST. This does NOT create a leg.
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reconcile_leg'])) {
 if (!mfo_verify()) $error='Token non valido';
 elseif (empty($_SESSION['mfo_leg_block_'.$id])) $error='Nessun blocco da riconciliare';
 else {
  $reconciledLeg=filter_var($_POST['reconcile_leg'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if (!$reconciledLeg) $error='Indicare un Leg ID valido';
  else {
   $check=$conn->prepare('SELECT slot_id FROM voy_mfo_slots WHERE activity_id=? AND activity_leg_id=? LIMIT 1');
   if (!$check) $error='Impossibile verificare lo slot MFO';
   else {
    $check->bind_param('ii',$id,$reconciledLeg);
    $check->execute(); $check->store_result(); $registered=$check->num_rows===1; $check->close();
    if (!$registered) $error='Tratta non registrata per questo programma MFO: blocco mantenuto';
    else {
     try {
      $remote=mfo_leg_activity($reconciledLeg);
      if (!$remote) $error='Tratta non verificabile su VaBase: blocco mantenuto';
      else {
       // Accept common API casing when present; reject an explicit mismatch.
       $remoteActivity=$remote->activityId ?? $remote->ActivityId ?? $remote->activity_id ?? null;
       if ($remoteActivity!==null && (int)$remoteActivity!==$id) $error='La tratta VaBase appartiene a un altro programma';
       else { unset($_SESSION['mfo_leg_block_'.$id]); header('Location: create_leg.php?id='.$id); exit; }
      }
     } catch (Throwable $e) { error_log('[MFO reconcile] activity='.$id.' leg='.$reconciledLeg.' exception='.get_class($e)); $error='Verifica VaBase non riuscita: blocco mantenuto'; }
    }
   }
  }
 }
} elseif ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!empty($_SESSION['mfo_leg_block_'.$id])) $error='Creazione bloccata: riconciliare prima la tratta VaBase';
 elseif (!mfo_verify()) $error='Token non valido';
 else {
  $dep=strtoupper(trim((string)($_POST['departure']??'')));
  $arr=strtoupper(trim((string)($_POST['arrival']??'')));
  $desc=(string)($_POST['description']??''); $doc=json_decode($desc);
  if (!preg_match('/^[A-Z]{4}$/',$dep) || !preg_match('/^[A-Z]{4}$/',$arr) ||
      !isset($doc->blocks) || !is_array($doc->blocks) || !$doc->blocks) $error='ICAO o descrizione EditorJS non validi';
  else {
   $data=['ActivityId'=>$id,'FlightNumber'=>strtoupper(trim((string)($_POST['flightNumber']??''))),
    'DepartureIcao'=>$dep,'ArrivalIcao'=>$arr,'Route'=>strtoupper(trim((string)($_POST['route']??''))),
    'Duration'=>null,'Description'=>$desc,'Aircraft'=>strtoupper(trim((string)($_POST['aircraft']??''))),
    'AircraftReg'=>strtoupper(trim((string)($_POST['aircraftReg']??'')))];
   // Once the POST starts, an exception or unexpected response may still mean
   // that VaBase created the leg. Never retry without reconciliation.
   $_SESSION['mfo_leg_block_'.$id]=true;
   try {
    $res=\Proxy\Api\Api::sendSync('POST','v1/activity/leg',$data);
    $responseBody=(string)$res->getBody();
    $decoded=json_decode($responseBody,true);
    $responseFormat=trim($responseBody)==='' ? 'empty' : (json_last_error()===JSON_ERROR_NONE ? 'json:'.gettype($decoded) : 'non-json');
    $responseLength=strlen($responseBody);
    $responseKeys=is_array($decoded) ? implode(',',array_slice(array_keys($decoded),0,12)) : '-';
    // Log only metadata, never response contents, credentials or user-supplied data.
    error_log('[MFO leg diagnostic] activity='.$id.' http='.$res->getStatusCode().' format='.$responseFormat.' bytes='.$responseLength.' keys='.$responseKeys);
   } catch (Throwable $e) {
    error_log('[MFO leg diagnostic] activity='.$id.' request_exception='.get_class($e));
    $error='Esito VaBase non verificabile. Controllare le tratte prima di ripetere.';
   }
   if (isset($res) && $res->getStatusCode()===200) {
    // La sincronizzazione con voy_mfo_slots richiede l'ID leg restituito da VaBase.
    // Non inventare l'ID e non considerare lo slot disponibile fino alla registrazione.
    $body=trim((string)$res->getBody()," \t\r\n\" ");
    if (ctype_digit($body) && (int)$body>0) {
      $legId=(int)$body;
      $s=$conn->prepare("INSERT INTO voy_mfo_slots (activity_id,activity_leg_id,status_mfo) VALUES (?,?,'AVAILABLE')");
      if (!$s) { error_log('[MFO] leg '.$legId.' non registrata: prepare'); $error='Tratta VaBase creata (#'.$legId.'), registrazione slot fallita: riconciliare, non ripetere'; }
      else { $s->bind_param('ii',$id,$legId); $ok=$s->execute(); $s->close();
       if ($ok) { header('Location: edit.php?id='.$id); exit; }
       error_log('[MFO] leg '.$legId.' non registrata: execute');
       $error='Tratta VaBase creata (#'.$legId.'), registrazione slot fallita: riconciliare, non ripetere';
      }
    } else $error='VaBase ha creato la tratta, ma non ha restituito un ID utilizzabile. Verificare prima di ripetere.';
    $_SESSION['mfo_leg_block_'.$id]=true;
   } elseif (isset($res)) $error='Risposta VaBase HTTP '.$res->getStatusCode().'. Verificare le tratte prima di ripetere.';
  }
 }
}
$blocked=!empty($_SESSION['mfo_leg_block_'.$id]);
?>
<?php include __DIR__.'/../includes/nav.php'; include __DIR__.'/../includes/sidebar.php'; ?>
<main><header class="page-header page-header-compact page-header-light border-bottom bg-white mb-4"><div class="container-fluid px-4"><div class="row pt-3"><div class="col mb-3"><h1 class="page-header-title">Nuova tratta MFO #<?=(int)$id?></h1></div><div class="col-auto"><a class="btn btn-light" href="edit.php?id=<?=(int)$id?>">Torna al programma</a></div></div></div></header>
<div class="container-fluid px-4">
<?php if($error): ?><div class="alert alert-danger"><?=mfo_e($error)?></div><?php endif; ?>
<?php if($blocked): ?><div class="alert alert-warning">Creazione bloccata dopo una risposta VaBase non riconciliata. Se la tratta e gia registrata in VOY e presente su VaBase, verifica il suo ID per sbloccare la creazione.</div>
<form method="post" class="card card-body mb-4"><input type="hidden" name="csrf_token" value="<?=mfo_e(mfo_token())?>"><label for="reconcile_leg" class="form-label">Leg ID gia riconciliato</label><input id="reconcile_leg" class="form-control mb-3" type="number" min="1" name="reconcile_leg" required><button type="submit" class="btn btn-outline-primary">Verifica e sblocca</button></form><?php else: ?>
<div class="card mb-4"><div class="card-body"><form method="post"><input type="hidden" name="csrf_token" value="<?=mfo_e(mfo_token())?>">
<div class="row"><div class="col-md-6 mb-3"><label>Partenza ICAO</label><input class="form-control" name="departure" maxlength="4" required></div><div class="col-md-6 mb-3"><label>Arrivo ICAO</label><input class="form-control" name="arrival" maxlength="4" required></div></div>
<div class="row"><div class="col-md-6 mb-3"><label>Numero volo</label><input class="form-control" name="flightNumber"></div><div class="col-md-6 mb-3"><label>Aeromobile ICAO</label><input class="form-control" name="aircraft"></div></div>
<div class="mb-3"><label>Rotta</label><input class="form-control" name="route"></div>
<div class="mb-3"><label>Registrazione aeromobile</label><input class="form-control" name="aircraftReg"></div>
<div class="mb-3"><label class="form-label">Descrizione</label><div id="editorJs" class="form-control" style="min-height:180px"></div><input type="hidden" name="description" id="mfoDescription" value="<?=mfo_e($_POST['description']??'')?>"></div>
<button class="btn btn-primary">Crea tratta e registra slot</button></form></div></div><?php endif; ?>
</div></main>
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

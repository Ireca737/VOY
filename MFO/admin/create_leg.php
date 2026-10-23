<?php
require_once __DIR__.'/_common.php';
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
if (!$id || !mfo_program($conn,$id)) { http_response_code(404); exit('Programma MFO non trovato'); }
$r=\Proxy\Api\Api::sendSync('GET','v1/activity/'.$id,null);
if ($r->getStatusCode()!==200) { http_response_code(502); exit('Activity VaBase non disponibile'); }
$a=json_decode($r->getBody());
if (!$a || $a->type!=='Event') { http_response_code(409); exit('Activity non valida'); }
$error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
 if (!mfo_verify()) $error='Token non valido';
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
   $res=\Proxy\Api\Api::sendSync('POST','v1/activity/leg',$data);
   if ($res->getStatusCode()===200) {
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
   } else $error='VaBase non ha accettato la tratta';
  }
 }
}
$blocked=!empty($_SESSION['mfo_leg_block_'.$id]);
?>
<?php include __DIR__.'/../includes/nav.php'; include __DIR__.'/../includes/sidebar.php'; ?>
<main><header class="page-header page-header-compact page-header-light border-bottom bg-white mb-4"><div class="container-fluid px-4"><div class="row pt-3"><div class="col mb-3"><h1 class="page-header-title">Nuova tratta MFO #<?=(int)$id?></h1></div><div class="col-auto"><a class="btn btn-light" href="edit.php?id=<?=(int)$id?>">Torna al programma</a></div></div></div></header>
<div class="container-fluid px-4">
<?php if($error): ?><div class="alert alert-danger"><?=mfo_e($error)?></div><?php endif; ?>
<?php if($blocked): ?><div class="alert alert-warning">Creazione bloccata per questa sessione dopo una risposta VaBase non riconciliata. Verificare gli ID prima di procedere.</div><?php else: ?>
<div class="card mb-4"><div class="card-body"><form method="post"><input type="hidden" name="csrf_token" value="<?=mfo_e(mfo_token())?>">
<div class="row"><div class="col-md-6 mb-3"><label>Partenza ICAO</label><input class="form-control" name="departure" maxlength="4" required></div><div class="col-md-6 mb-3"><label>Arrivo ICAO</label><input class="form-control" name="arrival" maxlength="4" required></div></div>
<div class="row"><div class="col-md-6 mb-3"><label>Numero volo</label><input class="form-control" name="flightNumber"></div><div class="col-md-6 mb-3"><label>Aeromobile ICAO</label><input class="form-control" name="aircraft"></div></div>
<div class="mb-3"><label>Rotta</label><input class="form-control" name="route"></div>
<div class="mb-3"><label>Registrazione aeromobile</label><input class="form-control" name="aircraftReg"></div>
<div class="mb-3"><label>Descrizione EditorJS (JSON)</label><textarea class="form-control" name="description" rows="5" required></textarea></div>
<button class="btn btn-primary">Crea tratta e registra slot</button></form></div></div><?php endif; ?>
</div></main>
<?php include __DIR__.'/../includes/footer.php'; ?>

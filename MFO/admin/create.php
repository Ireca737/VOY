<?php
/**
 * VOY MFO - creazione programma.
 * Destinazione: /public_html/admin/mfo/create.php
 * La Activity VaBase resta il record principale; VOY registra l'appartenenza a MFO.
 */
require_once __DIR__ . '/../../lib/functions.php';
require_once __DIR__ . '/../../proxy/api.php';
require_once __DIR__ . '/../../config.php';

session_start();

use Proxy\Api\Api;

Api::__constructStatic();
validateAdminSession();
if (!userHasPermission(11)) {
    header('Location: ' . website_base_url . 'admin/access_denied.php');
    exit();
}

// Limited production pilot: allow only GET, HEAD and POST on program creation.
// All other MFO write endpoints remain gated during testing.
if (!in_array(($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD', 'POST'], true)) { http_response_code(405); exit('Metodo non consentito'); }

function mfoCreateEsc($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$error = null;
$createdActivityId = null;
$activityRequestAttempted = false;
$creationOutcomeUncertain = false;
$values = [
    'title' => '', 'description' => '', 'start' => '',
    'end' => '', 'active' => '1', 'cash_prize' => '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['title', 'description', 'start', 'end', 'cash_prize'] as $key) {
        $values[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    $values['active'] = isset($_POST['active']) ? '1' : '0';
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    $expectedToken = (string) ($_SESSION['mfo_create_csrf'] ?? '');

    if (!empty($_SESSION['mfo_create_reconciliation_required'])) {
        $error = 'Creazione bloccata: riconciliazione amministrativa richiesta prima di un nuovo tentativo.';
    } elseif (!$expectedToken || !hash_equals($expectedToken, $submittedToken)) {
        $error = 'Sessione del modulo scaduta. Ricaricare la pagina.';
    } elseif (!ctype_digit($values['cash_prize']) || (float)$values['cash_prize'] > 2147483647) {
        $error = 'Il montepremi Cash deve essere un intero compreso tra 0 e 2147483647.';
    } elseif ($values['title'] === '' || $values['description'] === '' || $values['start'] === '') {
        $error = 'Titolo, descrizione e data iniziale sono obbligatori.';
    } else {
        $description = json_decode($values['description']);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($description->blocks) ||
            !is_array($description->blocks) || count($description->blocks) === 0) {
            $error = 'La descrizione deve essere un documento EditorJS JSON con almeno un blocco.';
        }
    }

    if ($error === null) {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $values['start'], new DateTimeZone('UTC'));
        $end = $values['end'] !== ''
            ? DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $values['end'], new DateTimeZone('UTC'))
            : null;
        if (!$start || $start->format('Y-m-d\\TH:i') !== $values['start'] ||
            ($values['end'] !== '' && (!$end || $end->format('Y-m-d\\TH:i') !== $values['end'])) ||
            ($end && $end <= $start)) {
            $error = 'Controllare le date UTC: la scadenza deve essere successiva all’inizio.';
        }
    }

    if ($error === null) {
        try {
            // Verificare questo percorso sul server prima della pubblicazione.
            require_once __DIR__ . '/../../voy/secure_config/conn_voy.php';
            if (!isset($conn) || !($conn instanceof mysqli)) {
                throw new RuntimeException('Connessione VOY non disponibile.');
            }

            // Verifica preventiva del database: non creare Activity se il registro MFO non è accessibile.
            $probe = $conn->query('SELECT id FROM voy_mfo_programs LIMIT 0');
            if ($probe === false) {
                throw new RuntimeException('Tabella voy_mfo_programs non disponibile.');
            }
            $probe->free();

            $data = [
                'Title' => $values['title'],
                'Description' => $values['description'],
                'Type' => 'Event',
                'Banner' => '',
                'AwardId' => null,
                'BonusXp' => null,
                'StartDate' => $start->format('Y-m-d\\TH:i'),
                'EndDate' => $end ? $end->format('Y-m-d\\TH:i') : null,
                'Active' => $values['active'] === '1',
                'LegsInOrder' => false,
                'MinRankId' => null,
            ];
            // From this point a network error may conceal a successful remote creation.
            $activityRequestAttempted = true;
            $response = Api::sendSync('POST', 'v1/activity', $data);
            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException('Creazione Activity rifiutata da VaBase (HTTP ' . $response->getStatusCode() . ').');
            }
            $body = trim((string) $response->getBody(), " \t\n\r\0\x0B\" ");
            if (!ctype_digit($body) || (int) $body < 1) {
                throw new RuntimeException('VaBase ha risposto senza un Activity ID valido. Verificare prima di ripetere.');
            }
            $createdActivityId = (int) $body;

            $stmt = $conn->prepare('INSERT INTO voy_mfo_programs (activity_id, cash_prize) VALUES (?, ?)');
            if (!$stmt) {
                throw new RuntimeException('Impossibile preparare la registrazione MFO.');
            }
            $cashPrize = (int)$values['cash_prize'];
            $stmt->bind_param('ii', $createdActivityId, $cashPrize);
            $ok = $stmt->execute();
            $stmt->close();
            if (!$ok) {
                throw new RuntimeException('Registrazione nel registro MFO non riuscita.');
            }

            // Token monouso: una nuova richiesta POST non deve ricreare lo stesso programma.
            unset($_SESSION['mfo_create_csrf']);
            header('Location: ' . website_base_url . 'admin/mfo/index.php?created=' . $createdActivityId);
            exit();
        } catch (Throwable $e) {
            error_log('[MFO create] ' . $e->getMessage() .
                ($createdActivityId !== null ? ' | orphan activity_id=' . $createdActivityId : ''));
            $creationOutcomeUncertain = $activityRequestAttempted && $createdActivityId === null;
            $error = $createdActivityId !== null
                ? 'ATTENZIONE: Activity VaBase #' . $createdActivityId .
                  ' creata, ma registrazione MFO non confermata. Non ripetere la creazione: occorre riconciliare il record.'
                : ($creationOutcomeUncertain
                    ? 'ATTENZIONE: esito della creazione VaBase non verificabile. Non ripetere la creazione: controllare le Activity VaBase e riconciliare prima di procedere.'
                    : 'Creazione non completata: ' . $e->getMessage());
            if ($creationOutcomeUncertain || $createdActivityId !== null) {
                $_SESSION['mfo_create_reconciliation_required'] = true;
            }
            // Evita che un secondo invio ripeta una creazione VaBase potenzialmente già avvenuta.
            unset($_SESSION['mfo_create_csrf']);
        }
    }
}
if (empty($_SESSION['mfo_create_csrf']) && empty($_SESSION['mfo_create_reconciliation_required'])) {
    $_SESSION['mfo_create_csrf'] = bin2hex(random_bytes(32));
}
?>
<?php include __DIR__ . '/../includes/nav.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<main>
    <header class="page-header page-header-compact page-header-light border-bottom bg-white mb-4">
        <div class="container-fluid px-4">
            <div class="page-header-content">
                <div class="row align-items-center justify-content-between pt-3">
                    <div class="col-auto mb-3">
                        <h1 class="page-header-title"><div class="page-header-icon"><i data-feather="plus"></i></div>Nuovo programma MFO</h1>
                    </div>
                    <div class="col-auto mb-3"><a class="btn btn-sm btn-light text-primary" href="index.php">Torna ai programmi</a></div>
                </div>
            </div>
        </div>
    </header>
    <div class="container-fluid px-4">
        <?php if ($error !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo mfoCreateEsc($error); ?></div>
        <?php endif; ?>
        <div class="card mb-4">
            <div class="card-header">Dati programma</div>
            <div class="card-body">
                <p class="text-muted">MFO crea sempre una Activity VaBase di tipo Event con tratte senza ordine obbligatorio. Le date sono UTC.</p>
                <?php if ($createdActivityId === null && empty($_SESSION['mfo_create_reconciliation_required'])): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo mfoCreateEsc($_SESSION['mfo_create_csrf']); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="title">Titolo</label>
                        <input class="form-control" id="title" name="title" maxlength="200" required value="<?php echo mfoCreateEsc($values['title']); ?>">
                    </div>
                    <div class="mb-3"><label class="form-label">Descrizione</label><div id="editorJs" class="form-control" style="min-height:180px"></div><input type="hidden" name="description" id="mfoDescription" value="<?php echo mfoCreateEsc($values['description']); ?>"><div class="form-text">Editor grafico VaBase (EditorJS).</div></div><div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="start">Inizio (UTC)</label>
                            <input class="form-control" type="datetime-local" id="start" name="start" required value="<?php echo mfoCreateEsc($values['start']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="end">Fine (UTC, facoltativa)</label>
                            <input class="form-control" type="datetime-local" id="end" name="end" value="<?php echo mfoCreateEsc($values['end']); ?>">
                        </div>
                    </div>
                    <div class="mb-3"><label class="form-label" for="cash_prize">Montepremi Cash totale</label><input class="form-control" type="number" min="0" max="2147483647" step="1" id="cash_prize" name="cash_prize" required value="<?php echo mfoCreateEsc($values['cash_prize']); ?>"><div class="form-text">Premio complessivo del programma, ripartito tra i piloti aventi diritto. Inserire 0 se non previsto.</div></div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="active" name="active" value="1" <?php echo $values['active'] === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="active">Activity attiva</label>
                    </div>
                    <button class="btn btn-primary" type="submit">Crea programma MFO</button>
                </form>
                <?php else: ?>
                    <p>Creazione da verificare<?php if ($createdActivityId !== null): ?>: Activity VaBase #<?php echo (int) $createdActivityId; ?><?php endif; ?>. Non inviare nuovamente il modulo: è necessaria una riconciliazione amministrativa.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

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

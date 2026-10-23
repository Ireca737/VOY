<?php
/**
 * VOY MFO - amministrazione programmi (sola lettura).
 * Destinazione server: /public_html/admin/mfo/index.php
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

// Server-side pre-production gate: block every write before API or DB access.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') { http_response_code(403); exit('MFO in collaudo: scritture disabilitate'); }

$programs = [];
$error = null;
try {
    // Dal percorso /admin/mfo/ si risale a /admin/voy/secure_config/.
    require_once __DIR__ . '/../voy/secure_config/conn_voy.php';
    if (!isset($conn) || !($conn instanceof mysqli)) {
        throw new RuntimeException('Connessione VOY non disponibile.');
    }

    $result = $conn->query('SELECT id, activity_id, created_at FROM voy_mfo_programs ORDER BY id DESC');
    if ($result === false) {
        throw new RuntimeException('Impossibile leggere i programmi MFO.');
    }
    while ($row = $result->fetch_assoc()) {
        $programs[] = $row;
    }
    $result->free();

    // L'API VaBase è la fonte dei metadati delle Activity.
    $res = Api::sendSync('GET', 'v1/activities', null);
    if ($res->getStatusCode() !== 200) {
        throw new RuntimeException('API VaBase temporaneamente non disponibile.');
    }
    $activities = json_decode($res->getBody(), true);
    if (!is_array($activities)) {
        throw new RuntimeException('Risposta API VaBase non valida.');
    }
    $byId = [];
    foreach ($activities as $activity) {
        if (isset($activity['id'])) {
            $byId[(string) $activity['id']] = $activity;
        }
    }
    foreach ($programs as &$program) {
        $program['activity'] = $byId[(string) $program['activity_id']] ?? null;
    }
    unset($program);
} catch (Throwable $e) {
    error_log('[MFO admin] ' . $e->getMessage());
    $error = 'Non è stato possibile caricare i programmi MFO. Controllare la connessione VOY e le API VaBase.';
}

function mfoEsc($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="grid"></i></div>
                            Gestione MFO
                        </h1>
                    </div>
                    <div class="col-auto mb-3"><a class="btn btn-primary" href="create.php"><i data-feather="plus" class="me-1"></i>Nuovo programma MFO</a></div>
                </div>
            </div>
        </div>
    </header>
    <div class="container-fluid px-4">
        <?php if ($error !== null): ?>
            <div class="alert alert-danger" role="alert"><?php echo mfoEsc($error); ?></div>
        <?php else: ?>
            <div class="card mb-4">
                <div class="card-header">Programmi MFO</div>
                <div class="card-body">
                    <p class="text-muted">Programmi MFO registrati. La gestione delle tratte si apre dalla scheda del programma.</p>
                    <?php if (!$programs): ?>
                        <div class="alert alert-info">Nessun programma MFO registrato.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead><tr><th>ID MFO</th><th>Activity ID</th><th>Programma</th><th>Tipo</th><th>Attivo</th><th>Scadenza (UTC)</th><th>Azioni</th></tr></thead>
                                <tbody>
                                <?php foreach ($programs as $program):
                                    $activity = $program['activity'];
                                ?>
                                    <tr>
                                        <td><?php echo (int) $program['id']; ?></td>
                                        <td><?php echo (int) $program['activity_id']; ?></td>
                                        <td><?php echo $activity ? mfoEsc($activity['title'] ?? 'Senza titolo') : '<span class="text-warning">Activity non trovata in VaBase</span>'; ?></td>
                                        <td><?php echo $activity ? mfoEsc($activity['type'] ?? '') : '—'; ?></td>
                                        <td><?php echo $activity ? (filter_var($activity['active'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'Sì' : 'No') : '—'; ?></td>
                                        <td><?php echo $activity ? mfoEsc($activity['endDate'] ?? 'Nessuna scadenza') : '—'; ?></td>
                                        <td><a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?php echo (int) $program['activity_id']; ?>">Gestisci programma e tratte</a></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>

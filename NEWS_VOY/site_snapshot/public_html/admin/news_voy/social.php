<?php
/**
 * NEWS VOY — anteprima social (fase 1).
 * Nessuna chiamata alle API Meta e nessuna pubblicazione automatica.
 */
require_once __DIR__ . '/../../lib/functions.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/news_db.php';

session_start();
validateAdminSession();
if (!userHasPermission(3)) {
    header('Location: ' . website_base_url . 'admin/access_denied.php');
    exit();
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$article = $id ? voyNewsGetById($id) : null;
if (!$article || (int)$article['is_public'] !== 1) {
    http_response_code(404);
    exit('Notizia pubblica non trovata.');
}

$title = (string)$article['title'];
$teaser = trim((string)($article['teaser'] ?? ''));
$summary = trim(preg_replace('/\s+/u', ' ', preg_replace('/^\s*[-•]\s*/mu', '', $teaser)));
$baseUrl = rtrim(website_base_url, '/') . '/';
$articleUrl = $baseUrl . 'news_voy_item.php?id=' . (int)$article['id'];
$imageUrl = $baseUrl . 'images/news_voy/news4voy.png'; // Copertina iniziale NEWS4VOY; da rendere configurabile.
$facebookText = trim($title . "\n\n" . $summary . "\n\n" . $articleUrl);
$instagramText = trim($title . "\n\n" . $summary . "\n\nArticolo completo sul sito Virtual Over Italy (link in bio).");
function socialEsc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<?php include '../includes/nav.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<main>
    <header class="page-header page-header-compact page-header-light border-bottom bg-white mb-4">
        <div class="container-fluid px-4">
            <div class="page-header-content pt-3 pb-3">
                <h1 class="page-header-title">Anteprima social — NEWS VOY</h1>
            </div>
        </div>
    </header>
    <div class="container-xl px-4">
        <div class="alert alert-info">
            Fase 1: prepara i testi e controlla l'anteprima. Nessun contenuto viene inviato a Facebook o Instagram.
        </div>
        <div class="card mb-4">
            <div class="card-header"><?php echo socialEsc($title); ?></div>
            <div class="card-body">
                <p><strong>Articolo originale:</strong>
                    <a href="<?php echo socialEsc($articleUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo socialEsc($articleUrl); ?></a>
                </p>
                <div class="mb-3">
                    <label class="form-label" for="fbText">Facebook — testo proposto</label>
                    <textarea class="form-control" id="fbText" rows="6"><?php echo socialEsc($facebookText); ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="igText">Instagram — testo proposto</label>
                    <textarea class="form-control" id="igText" rows="6"><?php echo socialEsc($instagramText); ?></textarea>
                    <div class="form-text">Instagram richiede un contenuto multimediale idoneo; i link nelle didascalie non sono generalmente cliccabili.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Copertina proposta (da verificare)</label><br>
                    <img src="<?php echo socialEsc($imageUrl); ?>" alt="Copertina NEWS VOY" style="max-width:420px;width:100%;height:auto" loading="lazy">
                </div>
                <button class="btn btn-primary" type="button" disabled title="Integrazione Meta non ancora configurata">Pubblica sui social — prossimamente</button>
                <a class="btn btn-outline-secondary ms-2" href="<?php echo socialEsc($baseUrl . 'admin/news_voy/index.php'); ?>">Torna alle news</a>
            </div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>

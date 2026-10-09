<?php
/**
 * VOY Social — anteprima testate (fase 2).
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
// Solo NEWS4VOY usa la sua copertina; tutte le altre news usano la Bacheca.
$isNews4Voy = strncasecmp(ltrim($title), 'NEWS4VOY', 8) === 0;
$testata = $isNews4Voy ? 'NEWS4VOY' : 'LA BACHECA DI COMPAGNIA';
$imageUrl = $isNews4Voy ? $baseUrl . 'images/news_voy/news4voy.png' : null;
$logoVoyUrl = $baseUrl . 'images/Logo%20VOI.jpg';
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
                    <label class="form-label">Testata selezionata automaticamente: <strong><?php echo socialEsc($testata); ?></strong></label>
                    <?php if ($isNews4Voy) { ?>
                        <div><img src="<?php echo socialEsc($imageUrl); ?>" alt="Testata NEWS4VOY" style="max-width:420px;width:100%;height:auto" loading="lazy"></div>
                    <?php } else { ?>
                        <!-- Anteprima HTML della testata Bacheca; non e' ancora un'immagine social. -->
                        <div style="padding:24px 30px 20px;border-bottom:3px solid #137eae;background:#fff;max-width:850px;">
                            <div style="display:flex;align-items:center;gap:22px;flex-wrap:wrap;">
                                <div style="flex:0 0 auto;"><img src="<?php echo socialEsc($logoVoyUrl); ?>" alt="Virtual Over Italy" style="display:block;width:105px;height:auto;"></div>
                                <div style="flex:1 1 420px;min-width:250px;">
                                    <div style="font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#137eae;margin-bottom:6px;">Virtual Over Italy</div>
                                    <div style="font-size:32px;line-height:1.1;font-weight:700;color:#1f2b36;">LA BACHECA DI COMPAGNIA</div>
                                    <div style="font-size:15px;color:#6d7780;margin-top:5px;">Avvisi e comunicazioni da Virtual Over Italy</div>
                                </div>
                            </div>
                        </div>
                        <div class="form-text">Questa e' un'anteprima HTML: prima della pubblicazione su Instagram servira' una copertina JPG/PNG.</div>
                    <?php } ?>
                </div>
                <button class="btn btn-primary" type="button" disabled title="Integrazione Meta non ancora configurata">Pubblica sui social — prossimamente</button>
                <a class="btn btn-outline-secondary ms-2" href="<?php echo socialEsc($baseUrl . 'admin/news_voy/index.php'); ?>">Torna alle news</a>
            </div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>

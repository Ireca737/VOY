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

/**
 * Pubblica sulla Pagina Facebook.
 * Il token ricevuto e' il System User token VOY_news: da questo viene
 * ricavato il Page Access Token, usato poi per POST /feed.
 */
function voyPublishFacebook(string $pageId, string $systemToken, string $message): array {
    $accountsUrl = 'https://graph.facebook.com/v26.0/me/accounts'
        . '?fields=id,name,access_token'
        . '&access_token=' . urlencode($systemToken);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $accountsUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'http_code' => $httpCode, 'post_id' => null,
            'error' => 'Errore cURL durante il recupero della Pagina: ' . $curlError];
    }

    $data = json_decode($response, true);
    if ($httpCode < 200 || $httpCode >= 300 || !isset($data['data']) || !is_array($data['data'])) {
        return ['ok' => false, 'http_code' => $httpCode, 'post_id' => null,
            'error' => 'Impossibile ottenere le Pagine disponibili per VOY_news.'];
    }

    $pageToken = '';
    foreach ($data['data'] as $page) {
        if ((string)($page['id'] ?? '') === $pageId) {
            $pageToken = trim((string)($page['access_token'] ?? ''));
            break;
        }
    }
    if ($pageToken === '') {
        return ['ok' => false, 'http_code' => $httpCode, 'post_id' => null,
            'error' => 'Page Access Token della Pagina Virtual Over Italy non disponibile.'];
    }

    $publishUrl = 'https://graph.facebook.com/v26.0/' . rawurlencode($pageId) . '/feed';
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $publishUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['message' => $message, 'access_token' => $pageToken]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['ok' => false, 'http_code' => $httpCode, 'post_id' => null,
            'error' => 'Errore cURL durante la pubblicazione: ' . $curlError];
    }
    $data = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300 && is_array($data) && !empty($data['id'])) {
        return ['ok' => true, 'http_code' => $httpCode, 'post_id' => (string)$data['id'], 'error' => null];
    }
    $errorMessage = 'Errore sconosciuto restituito da Meta.';
    if (is_array($data) && isset($data['error']) && is_array($data['error'])) {
        $errorMessage = (string)($data['error']['message'] ?? $errorMessage);
        if (isset($data['error']['code'])) $errorMessage .= ' [codice ' . (string)$data['error']['code'] . ']';
    }
    return ['ok' => false, 'http_code' => $httpCode, 'post_id' => null, 'error' => $errorMessage];
}

if (!isset($_SESSION['voy_social_csrf']) || !is_string($_SESSION['voy_social_csrf'])) {
    $_SESSION['voy_social_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['voy_social_csrf'];
$facebookResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish_facebook') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    $postedFacebookText = trim((string)($_POST['facebook_text'] ?? ''));
    if ($postedCsrf === '' || !hash_equals($csrfToken, $postedCsrf)) {
        $facebookResult = ['ok' => false, 'error' => 'Sessione non valida o richiesta scaduta. Ricarica la pagina e riprova.'];
    } elseif ($postedFacebookText === '') {
        $facebookResult = ['ok' => false, 'error' => 'Il testo Facebook non puo\\' essere vuoto.'];
    } else {
        $privateConfigFile = '/home/virtualoveritaly/voy_private/voy_social_config.php';
        if (!is_readable($privateConfigFile)) {
            $facebookResult = ['ok' => false, 'error' => 'Configurazione privata VOY Social non leggibile.'];
        } else {
            $socialConfig = require $privateConfigFile;
            $pageId = trim((string)($socialConfig['facebook_page_id'] ?? ''));
            $accessToken = trim((string)($socialConfig['meta_access_token'] ?? ''));
            $freshArticle = voyNewsGetById((int)$article['id']);
            if ($pageId === '' || $accessToken === '') {
                $facebookResult = ['ok' => false, 'error' => 'Configurazione Meta incompleta.'];
            } elseif (!$freshArticle || (int)$freshArticle['is_public'] !== 1) {
                $facebookResult = ['ok' => false, 'error' => 'La notizia non risulta piu\\' pubblica. Pubblicazione annullata.'];
            } else {
                $facebookResult = voyPublishFacebook($pageId, $accessToken, $postedFacebookText);
                $facebookText = $postedFacebookText;
            }
        }
    }
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
        <?php if ($facebookResult !== null) { ?>
            <div class="alert alert-<?php echo !empty($facebookResult['ok']) ? 'success' : 'danger'; ?>">
                <strong><?php echo !empty($facebookResult['ok']) ? 'Pubblicazione Facebook completata.' : 'Pubblicazione Facebook non riuscita.'; ?></strong>
                <?php if (!empty($facebookResult['post_id'])) { ?><br>ID post Meta: <code><?php echo socialEsc((string)$facebookResult['post_id']); ?></code><?php } ?>
                <?php if (empty($facebookResult['ok'])) { ?><br><?php echo socialEsc((string)($facebookResult['error'] ?? 'Errore sconosciuto.')); ?><?php } ?>
            </div>
        <?php } ?>
        <div class="alert alert-info">Facebook e' collegato a VOY Social. Controlla il testo prima della pubblicazione. Instagram e' ancora in fase di configurazione.</div>
        <div class="card mb-4">
            <div class="card-header"><?php echo socialEsc($title); ?></div>
            <div class="card-body">
                <p><strong>Articolo originale:</strong>
                    <a href="<?php echo socialEsc($articleUrl); ?>" target="_blank" rel="noopener noreferrer"><?php echo socialEsc($articleUrl); ?></a>
                </p>
                <form method="post" action="<?php echo socialEsc($baseUrl . 'admin/news_voy/social.php?id=' . (int)$article['id']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo socialEsc($csrfToken); ?>">
                    <input type="hidden" name="action" value="publish_facebook">
                    <div class="mb-3">
                        <label class="form-label" for="fbText">Facebook — testo proposto</label>
                        <textarea class="form-control" id="fbText" name="facebook_text" rows="6"><?php echo socialEsc($facebookText); ?></textarea>
                    </div>
                    <div class="mb-4"><button class="btn btn-primary" type="submit" onclick="return confirm('Pubblicare ora questo contenuto sulla pagina Facebook Virtual Over Italy?');">Pubblica su Facebook</button></div>
                </form>
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
                <button class="btn btn-outline-primary" type="button" disabled title="Pubblicazione Instagram non ancora attiva">Pubblica su Instagram — prossimamente</button>
                <a class="btn btn-outline-secondary ms-2" href="<?php echo socialEsc($baseUrl . 'admin/news_voy/index.php'); ?>">Torna alle news</a>
            </div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>

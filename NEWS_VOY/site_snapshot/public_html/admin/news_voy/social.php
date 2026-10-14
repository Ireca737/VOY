<?php
/** VOY Social - Facebook invariato; Instagram con copertina social. */
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
$isNews4Voy = strncasecmp(ltrim($title), 'NEWS4VOY', 8) === 0;
$testata = $isNews4Voy ? 'NEWS4VOY' : 'LA BACHECA DI COMPAGNIA';
$socialImageUrl = $baseUrl . 'images/news_voy/' . ($isNews4Voy ? 'news4voy_social.jpg' : 'bacheca_voy_social.jpg');
$facebookText = trim($title . "\n\n" . $summary . "\n\n" . $articleUrl);
$instagramText = trim($title . "\n\n" . $summary . "\n\nArticolo completo sul sito Virtual Over Italy (link in bio).");
function socialEsc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Facebook: carica una foto non pubblicata e la allega a un vero post nel feed. */
function voyPublishFacebook(string $pageId, string $systemToken, string $message, string $imageUrl): array {
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

    $root = 'https://graph.facebook.com/v26.0/' . rawurlencode($pageId);
    $request = static function (string $url, array $fields) use ($pageToken): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields + ['access_token' => $pageToken]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        $data = is_string($response) ? json_decode($response, true) : null;
        $error = $response === false ? 'Errore di rete: ' . $curlError : 'Risposta non valida da Meta.';
        if (is_array($data) && isset($data['error'])) {
            $error = (string)($data['error']['message'] ?? $error);
            if (isset($data['error']['code'])) $error .= ' [codice ' . $data['error']['code'] . ']';
        }
        return ['ok' => $httpCode >= 200 && $httpCode < 300 && is_array($data) && !isset($data['error']),
            'data' => $data, 'http_code' => $httpCode, 'error' => $error];
    };

    // La foto non deve generare un post autonomo nella sezione Foto.
    $photo = $request($root . '/photos', [
        'url' => $imageUrl,
        'published' => 'false',
    ]);
    $photoId = (string)($photo['data']['id'] ?? '');
    if (!$photo['ok'] || $photoId === '') {
        return ['ok' => false, 'http_code' => $photo['http_code'], 'post_id' => null,
            'error' => 'Caricamento copertina non riuscito: ' . $photo['error']];
    }

    // Un solo post nel feed con la copertina allegata.
    $feed = $request($root . '/feed', [
        'message' => $message,
        'attached_media[0]' => json_encode(['media_fbid' => $photoId], JSON_UNESCAPED_SLASHES),
        'published' => 'true',
    ]);
    $postId = (string)($feed['data']['id'] ?? '');
    if (!$feed['ok'] || $postId === '') {
        return ['ok' => false, 'http_code' => $feed['http_code'], 'post_id' => null,
            'error' => 'Copertina caricata (ID ' . $photoId . '), ma creazione del post non confermata: '
                . $feed['error'] . '. Verificare Facebook prima di riprovare per evitare duplicati.'];
    }
    return ['ok' => true, 'http_code' => $feed['http_code'], 'post_id' => $postId, 'error' => null];
}

/** Richieste Instagram Graph API con token solo nell'header HTTP. */
function voyIgRequest(string $url, string $token, ?array $fields = null): array {
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ];
    if ($fields !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($fields);
    }
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    $data = is_string($response) ? json_decode($response, true) : null;
    $error = $response === false ? 'Errore di rete: ' . $curlError : 'Risposta non valida da Meta.';
    if (is_array($data) && isset($data['error']) && is_array($data['error'])) {
        $error = (string)($data['error']['message'] ?? $error);
        if (isset($data['error']['code'])) $error .= ' [codice ' . (string)$data['error']['code'] . ']';
    }
    return [
        'ok' => $httpCode >= 200 && $httpCode < 300 && is_array($data) && !isset($data['error']),
        'data' => $data,
        'error' => str_replace($token, '[TOKEN]', $error),
    ];
}

/** Instagram via Facebook Login: crea container, attende FINISHED, pubblica. */
function voyPublishInstagram(string $pageId, string $token, string $caption, string $imageUrl): array {
    $page = voyIgRequest('https://graph.facebook.com/v26.0/' . rawurlencode($pageId)
        . '?fields=instagram_business_account', $token);
    $igId = (string)($page['data']['instagram_business_account']['id'] ?? '');
    if (!$page['ok'] || $igId === '') {
        return ['ok' => false, 'error' => 'Account Instagram non disponibile: ' . $page['error']];
    }
    $root = 'https://graph.facebook.com/v26.0/' . rawurlencode($igId);
    $created = voyIgRequest($root . '/media', $token, [
        'image_url' => $imageUrl,
        'caption' => $caption,
    ]);
    $creationId = (string)($created['data']['id'] ?? '');
    if (!$created['ok'] || $creationId === '') {
        return ['ok' => false, 'error' => 'Creazione immagine: ' . $created['error']];
    }
    $status = '';
    for ($attempt = 0; $attempt < 8; $attempt++) {
        if ($attempt > 0) sleep(2);
        $check = voyIgRequest('https://graph.facebook.com/v26.0/' . rawurlencode($creationId)
            . '?fields=status_code', $token);
        if (!$check['ok']) {
            return ['ok' => false, 'error' => 'Verifica immagine: ' . $check['error']];
        }
        $status = (string)($check['data']['status_code'] ?? '');
        if ($status === 'FINISHED') break;
        if ($status === 'ERROR' || $status === 'EXPIRED') break;
    }
    if ($status !== 'FINISHED') {
        return ['ok' => false, 'error' => 'Immagine non pronta (' . $status
            . '). Container ID ' . $creationId . '. Verificare lo stato prima di riprovare.'];
    }
    $published = voyIgRequest($root . '/media_publish', $token, ['creation_id' => $creationId]);
    $postId = (string)($published['data']['id'] ?? '');
    if (!$published['ok'] || $postId === '') {
        return ['ok' => false, 'error' => 'Pubblicazione Instagram: ' . $published['error']
            . '. Container ID ' . $creationId . '. Verificare prima di riprovare.'];
    }
    return ['ok' => true, 'post_id' => $postId];
}

if (!isset($_SESSION['voy_social_csrf']) || !is_string($_SESSION['voy_social_csrf'])) {
    $_SESSION['voy_social_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['voy_social_csrf'];
$facebookResult = null;
$instagramResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish_facebook') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    $postedFacebookText = trim((string)($_POST['facebook_text'] ?? ''));
    if ($postedCsrf === '' || !hash_equals($csrfToken, $postedCsrf)) {
        $facebookResult = ['ok' => false, 'error' => 'Sessione non valida o richiesta scaduta. Ricarica la pagina e riprova.'];
    } elseif ($postedFacebookText === '') {
        $facebookResult = ['ok' => false, 'error' => "Il testo Facebook non puo' essere vuoto."];
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
                $facebookResult = ['ok' => false, 'error' => "La notizia non risulta piu' pubblica. Pubblicazione annullata."];
            } else {
                $facebookResult = voyPublishFacebook($pageId, $accessToken, $postedFacebookText, $socialImageUrl);
                $facebookText = $postedFacebookText;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish_instagram') {
    $postedCsrf = (string)($_POST['csrf_token'] ?? '');
    $postedInstagramText = trim((string)($_POST['instagram_text'] ?? ''));
    $privateConfigFile = '/home/virtualoveritaly/voy_private/voy_social_config.php';
    $freshArticle = voyNewsGetById((int)$article['id']);
    if ($postedCsrf === '' || !hash_equals($csrfToken, $postedCsrf)) {
        $instagramResult = ['ok' => false, 'error' => 'Sessione non valida. Ricarica la pagina.'];
    } elseif ($postedInstagramText === '') {
        $instagramResult = ['ok' => false, 'error' => 'La didascalia Instagram non puo essere vuota.'];
    } elseif (!$freshArticle || (int)$freshArticle['is_public'] !== 1) {
        $instagramResult = ['ok' => false, 'error' => 'La notizia non risulta piu pubblica. Pubblicazione annullata.'];
    } elseif (!is_readable($privateConfigFile)) {
        $instagramResult = ['ok' => false, 'error' => 'Configurazione privata VOY Social non leggibile.'];
    } else {
        $socialConfig = require $privateConfigFile;
        $pageId = trim((string)($socialConfig['facebook_page_id'] ?? ''));
        $accessToken = trim((string)($socialConfig['meta_access_token'] ?? ''));
        if ($pageId === '' || $accessToken === '') {
            $instagramResult = ['ok' => false, 'error' => 'Configurazione Meta incompleta.'];
        } else {
            $instagramResult = voyPublishInstagram($pageId, $accessToken, $postedInstagramText, $socialImageUrl);
            $instagramText = $postedInstagramText;
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
        <?php if ($instagramResult !== null) { ?>
            <div class="alert alert-<?php echo !empty($instagramResult['ok']) ? 'success' : 'danger'; ?>">
                <strong><?php echo !empty($instagramResult['ok']) ? 'Pubblicazione Instagram completata.' : 'Pubblicazione Instagram non riuscita.'; ?></strong>
                <?php if (!empty($instagramResult['post_id'])) { ?><br>ID post Meta: <code><?php echo socialEsc((string)$instagramResult['post_id']); ?></code><?php } ?>
                <?php if (empty($instagramResult['ok'])) { ?><br><?php echo socialEsc((string)($instagramResult['error'] ?? 'Errore sconosciuto.')); ?><?php } ?>
            </div>
        <?php } ?>
        <div class="alert alert-info">Facebook pubblica copertina social JPG, testo e link diretto. Instagram pubblica la copertina social JPG con didascalia.</div>
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
                <form method="post" action="<?php echo socialEsc($baseUrl . 'admin/news_voy/social.php?id=' . (int)$article['id']); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo socialEsc($csrfToken); ?>">
                    <input type="hidden" name="action" value="publish_instagram">
                    <div class="mb-3">
                        <label class="form-label" for="igText">Instagram — didascalia proposta</label>
                        <textarea class="form-control" id="igText" name="instagram_text" rows="6"><?php echo socialEsc($instagramText); ?></textarea>
                        <div class="form-text">I link nelle didascalie Instagram non sono generalmente cliccabili.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Testata selezionata automaticamente: <strong><?php echo socialEsc($testata); ?></strong></label>
                        <div><img src="<?php echo socialEsc($socialImageUrl); ?>" alt="Copertina social" style="max-width:540px;width:100%;height:auto" loading="lazy"></div>
                        <div class="form-text">Immagine utilizzata per la pubblicazione Instagram.</div>
                    </div>
                    <button class="btn btn-outline-primary" type="submit" onclick="return confirm('Pubblicare ora questa immagine su Instagram @virtualoveritaly?');">Pubblica su Instagram</button>
                </form>
                <a class="btn btn-outline-secondary ms-2" href="<?php echo socialEsc($baseUrl . 'admin/news_voy/index.php'); ?>">Torna alle news</a>
            </div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../../lib/functions.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/news_db.php';

session_start();

validateAdminSession();

if (!userHasPermission(3)) {
    header('Location: ' . website_base_url . 'admin/access_denied.php');
    exit();
}

$status = null;
$title = null;
$teaser = null;
$poster = $_SESSION['name'] ?? 'System';
$news = null;
$responseMessage = null;
$isPublic = 0;

/*
 * Template RAW disponibili.
 * La whitelist evita di accettare percorsi arbitrari dalla query string.
 */
$templateKey = $_GET['template'] ?? '';
$templateHtml = '';
$templateLabel = 'Raw HTML';

$templates = [
    'news4voy' => [
        'file' => __DIR__ . '/templates/template_news4voy.html',
        'label' => 'NEWS4VOY'
    ],
    'bacheca' => [
        'file' => __DIR__ . '/templates/template_bacheca_compagnia.html',
        'label' => 'La Bacheca di Compagnia'
    ]
];

if ($_SERVER["REQUEST_METHOD"] !== "POST" && isset($templates[$templateKey])) {
    $templateFile = $templates[$templateKey]['file'];
    $templateLabel = $templates[$templateKey]['label'];

    if (is_file($templateFile) && is_readable($templateFile)) {
        $templateHtml = file_get_contents($templateFile);
        if ($templateHtml === false) {
            $templateHtml = '';
        }
    } else {
        error_log("news_voy index_raw.php: template non disponibile: " . $templateFile);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = cleanString($_POST['title']);
    $teaser = cleanString($_POST['teaser'] ?? '');
    $poster = cleanString($_POST['poster']);
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $encodedContent = $_POST['editorContentB64'] ?? '';
    $htmlContent = base64_decode($encodedContent, true);
    if ($htmlContent === false) {
        $htmlContent = '';
    }

    // incapsula in formato JSON compatibile con Editor.js (type: raw)
    $news = json_encode([
        'blocks' => [
            ['type' => 'raw', 'data' => ['html' => $htmlContent]]
        ]
    ], JSON_UNESCAPED_UNICODE);

    if (empty($title) || empty($htmlContent)) {
        $status = "required_fields";
    } else {
        try {
            voyNewsCreate($title, $teaser, $poster, $news, $isPublic);
            $status = "success";
            $title = "";
            $teaser = "";
            $news = "";
            $isPublic = 0;
        } catch (Throwable $e) {
            $status = "error";
            $responseMessage = "Database error while creating the article.";
            error_log("news_voy index_raw.php: " . $e->getMessage());
        }
    }
}
?>
<?php include '../includes/nav.php'; ?>
<?php include '../includes/sidebar.php'; ?>
<main>
    <header class="page-header page-header-compact page-header-light border-bottom bg-white mb-4">
        <div class="container-fluid px-4">
            <div class="page-header-content">
                <div class="row align-items-center justify-content-between pt-3">
                    <div class="col-auto mb-3">
                        <h1 class="page-header-title">
                            <div class="page-header-icon"><i data-feather="edit"></i></div>
                            Create <?php echo htmlspecialchars($templateLabel); ?> News
                        </h1>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4">
        <div class="card mb-4">
            <div class="card-header">
                Create <?php echo htmlspecialchars($templateLabel); ?> Article
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <a class="btn btn-outline-secondary me-2"
                        href="<?php echo website_base_url; ?>admin/news_voy/index.php">
                        Standard Editor
                    </a>
                    <a class="btn btn-outline-primary me-2"
                        href="<?php echo website_base_url; ?>admin/news_voy/index_raw.php?template=news4voy">
                        NEWS4VOY
                    </a>
                    <a class="btn btn-outline-primary"
                        href="<?php echo website_base_url; ?>admin/news_voy/index_raw.php?template=bacheca">
                        Bacheca di Compagnia
                    </a>
                </div>

                <?php if ($status == "success") { ?>
                    <div class="alert alert-success alert-dismissible fade show">Article successfully published.
                        <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
                    </div>
                <?php } elseif ($status == "error") { ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $responseMessage ?? 'An error occurred when creating the article.'; ?>
                        <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
                    </div>
                <?php } elseif ($status == "required_fields") { ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        Please complete all required fields.
                        <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
                    </div>
                <?php } ?>

                <form method="post" class="form" id="rawForm">
                    <div class="mb-3">
                        <label class="mb-1">Title*</label>
                        <input name="title" type="text" class="form-control"
                            value="<?php echo htmlspecialchars($title); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="mb-1" for="teaser">Teaser / sommario</label>
                        <textarea name="teaser" id="teaser" class="form-control" rows="3" maxlength="500"
                            placeholder="Breve testo di presentazione da mostrare nel widget delle news..."><?php echo htmlspecialchars(strval($teaser)); ?></textarea>
                        <div class="form-text">Testo breve dello strillone. Non modifica il contenuto dell'articolo.</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_public" id="is_public" value="1" <?php echo $isPublic ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_public">
                                Pubblica anche nella Home
                            </label>
                        </div>
                        <div class="form-text">Se non selezionata, la news resta visibile solo ai piloti autenticati.</div>
                    </div>

                    <div class="mb-3">
                        <label class="mb-1">Article (HTML code)*</label>
                        <textarea id="editorContent" rows="24"
                            class="form-control" placeholder="Insert your HTML code here..."><?php
                            if (!empty($news)) {
                                $decoded = json_decode($news, true);
                                echo htmlspecialchars($decoded['blocks'][0]['data']['html'] ?? '');
                            } elseif (!empty($templateHtml)) {
                                echo htmlspecialchars($templateHtml);
                            }
                        ?></textarea>
                        <input type="hidden" name="editorContentB64" id="editorContentB64">
                    </div>

                    <input type="hidden" name="poster" value="<?php echo htmlspecialchars($poster); ?>">
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">Publish News</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php include '../includes/footer.php'; ?>
<script type="text/javascript">
function voyBase64EncodeUtf8(value) {
    return btoa(unescape(encodeURIComponent(value)));
}
document.getElementById('rawForm').addEventListener('submit', function(e) {
    document.getElementById('editorContentB64').value = voyBase64EncodeUtf8(document.getElementById('editorContent').value);
});
</script>
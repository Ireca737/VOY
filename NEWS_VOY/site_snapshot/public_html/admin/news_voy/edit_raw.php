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

$id = cleanString($_GET['id'] ?? '');
if (empty($id)) {
    die("Missing news ID");
}

try {
    $data = voyNewsGetById((int)$id);
} catch (Throwable $e) {
    error_log("news_voy edit_raw.php GET: " . $e->getMessage());
    die("Database error");
}

if (!$data) {
    die("News not found");
}
$title = $data['title'] ?? '';
$teaser = $data['teaser'] ?? '';
$poster = $data['poster'] ?? ($_SESSION['name'] ?? 'System');
$newsJson = $data['news'] ?? '';
$isPublic = !empty($data['is_public']) ? 1 : 0;
$decoded = json_decode($newsJson, true);
$htmlContent = $decoded['blocks'][0]['data']['html'] ?? $newsJson;

$status = null;
$responseMessage = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = cleanString($_POST['title']);
    $teaser = cleanString($_POST['teaser'] ?? '');
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $encodedContent = $_POST['editorContentB64'] ?? '';
    $htmlContent = base64_decode($encodedContent, true);
    if ($htmlContent === false) {
        $htmlContent = '';
    }

    $newsJson = json_encode([
        'blocks' => [
            ['type' => 'raw', 'data' => ['html' => $htmlContent]]
        ]
    ], JSON_UNESCAPED_UNICODE);

    if (empty($title) || empty($htmlContent)) {
        $status = "required_fields";
    } else {
        try {
            voyNewsUpdate((int)$id, $title, $teaser, $newsJson, $isPublic);
            $status = "success";
        } catch (Throwable $e) {
            $status = "error";
            $responseMessage = "Database error while updating the article.";
            error_log("news_voy edit_raw.php UPDATE: " . $e->getMessage());
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
                            <div class="page-header-icon"><i data-feather="edit-2"></i></div>
                            Edit Raw HTML News
                        </h1>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="container-xl px-4">
        <div class="card mb-4">
            <div class="card-header">Edit Raw HTML Article</div>
            <div class="card-body">
                <?php if ($status == "success") { ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        News successfully updated.
                        <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
                    </div>
                <?php } elseif ($status == "error") { ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo $responseMessage ?? 'Error updating article.'; ?>
                        <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
                    </div>
                <?php } elseif ($status == "required_fields") { ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        Please complete all required fields.
                        <button class="btn-close" type="button" data-bs-dismiss="alert"></button>
                    </div>
                <?php } ?>

                <form method="post" class="form" id="editRawForm">
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
                        <textarea id="editorContent" rows="18"
                            class="form-control"><?php echo htmlspecialchars($htmlContent); ?></textarea>
                        <input type="hidden" name="editorContentB64" id="editorContentB64">
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
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
document.getElementById('editRawForm').addEventListener('submit', function(e) {
    document.getElementById('editorContentB64').value = voyBase64EncodeUtf8(document.getElementById('editorContent').value);
});
</script>
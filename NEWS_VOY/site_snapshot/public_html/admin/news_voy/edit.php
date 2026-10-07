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

$id = (int)cleanString($_GET['id'] ?? '');
$news = null;

try {
    $newsRow = voyNewsGetById($id);
} catch (Throwable $e) {
    error_log("news_voy edit.php GET: " . $e->getMessage());
    $newsRow = null;
}

if ($newsRow) {
    $news = (object)$newsRow;
} else {
    header('Location: index.php');
    die();
}

$status = "";
$responseMessage = null;
$title = $news->title;
$teaser = $news->teaser ?? '';
$isPublic = !empty($news->is_public) ? 1 : 0;
$news = $news->news;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = cleanString($_POST['title']);
    $teaser = cleanString($_POST['teaser'] ?? '');
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $encodedNews = $_POST['editorContentB64'] ?? '';
    $news = base64_decode($encodedNews, true);
    if ($news === false) {
        $news = '';
    }

    $decodedNews = json_decode($news);
    $editorValid = is_object($decodedNews) && isset($decodedNews->blocks) && is_array($decodedNews->blocks);

    if (empty($title) || empty($news) || !$editorValid) {
        $status = "required_fields";
    }

    if (empty($status)) {
        try {
            voyNewsUpdate($id, trim($title), trim($teaser), trim($news), $isPublic);
            $status = "success";
        } catch (Throwable $e) {
            $status = "error";
            $responseMessage = "Database error while updating news.";
            error_log("news_voy edit.php UPDATE: " . $e->getMessage());
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
                            <div class="page-header-icon"><i data-feather="align-left"></i></div>
                            Edit News
                        </h1>
                    </div>
                    <div class="col-12 col-xl-auto mb-3">
                        <a class="btn btn-sm btn-light text-primary" href="index.php">
                            <i class="me-1" data-feather="arrow-left"></i>
                            Back to News
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- Main page content-->
    <div class="container-fluid px-4">
        <div class="card mb-4">
            <div class="card-header">News</div>
            <div class="card-body">
                <?php if ($status == "success") { ?>
                    <div class="alert alert-success alert-dismissible fade show">News has been updated.
                        <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php } else { ?>
                    <?php if (!empty($status)) { ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php
                            if ($status == 'error') {
                                if (!empty($responseMessage)) {
                                    echo $responseMessage;
                                } else {
                                    echo 'An error occurred when updating news. Please try again later.';
                                }
                            }
                            if ($status == 'required_fields') {
                                echo 'Please check all fields have been completed correctly.';
                            }
                            ?>
                            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                <?php } ?>
                <form method="post" class="form" id="editorForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="mb-1">Title*</label>
                        <input name="title" type="text" id="title" class="form-control"
                            value="<?php echo htmlspecialchars(strval($title)) ?>" required>
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
                        <label class="mb-1">Article*</label>
                        <div id="editorJs" class="form-control"></div>
                    </div>
                    <div class="mb-3">
                        <div class=" text-right">
                            <input name="editorContentB64" type="hidden" id="editorContentB64" />
                            <button type="submit" id="submitButton" class="btn btn-primary">Save
                                Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/editorjs@2.30.7"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/header@2.8.8"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/list@2.0.0"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/embed@2.7.6"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/raw@2.5.0"></script>
<script src="https://cdn.jsdelivr.net/npm/@editorjs/simple-image@1.6.0"></script>
<script src="https://cdn.jsdelivr.net/npm/editorjs-parser@1/build/Parser.browser.min.js"></script>
<script src="<?php echo website_base_url; ?>admin/news_voy/editor_voy.js?v=2"></script>
<?php include '../includes/footer.php'; ?>
<script type="text/javascript">
    contentJson = '<?php echo $news != null ? addslashes(preg_replace("/\r|\n/", "", $news)) : ""; ?>';
</script>
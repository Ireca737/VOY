<?php
require_once __DIR__ . '/../../lib/functions.php';
require_once __DIR__ . '/../../proxy/api.php';
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/news_db.php';

session_start();

use Proxy\Api\Api;

Api::__constructStatic();

validateAdminSession();
if (!userHasPermission(3)) {
    header('Location: ' . website_base_url . 'admin/access_denied.php');
    exit();
}

$function = null;
if (isset($_GET['function'])) {
    $function = cleanString($_GET['function']);
}
if ($function == "delete_news") {
    try {
        voyNewsDelete((int)cleanString($_GET['id'] ?? ''));
    } catch (Throwable $e) {
        error_log("news_voy index.php DELETE: " . $e->getMessage());
    }
}

$status = null;
$title = null;
$teaser = null;
$news = null;
$poster = null;
$responseMessage = null;
$isPublic = 0;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $encodedNews = $_POST['editorContentB64'] ?? '';
    $news = base64_decode($encodedNews, true);
    if ($news === false) {
        $news = '';
    }
    $title = cleanString($_POST['title']);
    $teaser = cleanString($_POST['teaser'] ?? '');
    $poster = cleanString($_POST['poster']);
    $isPublic = isset($_POST['is_public']) ? 1 : 0;

    $decodedNews = json_decode($news);
    $editorValid = is_object($decodedNews) && isset($decodedNews->blocks) && is_array($decodedNews->blocks);

    if (empty($title) || empty($news) || !$editorValid) {
        $status = "required_fields";
    }

    if (empty($status)) {
        try {
            voyNewsCreate($title, $teaser, $poster, $news, $isPublic);
            $status = "success";

            // Manteniamo l'integrazione Discord gia' presente nel core.
            if (enable_discord_add_news_alerts) {
                $data = [
                    'Message' => 'A new NOTAM has been posted.'
                ];
                Api::sendSync('POST', 'v1/integrations/discord', $data);
            }

            $news = null;
            $title = null;
            $teaser = null;
            $isPublic = 0;
        } catch (Throwable $e) {
            $status = "error";
            $responseMessage = "Database error while creating the article.";
            error_log("news_voy index.php INSERT: " . $e->getMessage());
        }
    }
}

$newsArticles = [];
try {
    $newsArticles = voyNewsGetAll();
} catch (Throwable $e) {
    error_log("news_voy index.php SELECT: " . $e->getMessage());
}
if (!empty($newsArticles)) {
    foreach ($newsArticles as &$article) {

        $article["date"] = (new DateTime($article["date"]))->format('Y-m-d');
        $article["buttons"] = '<a class="btn btn-datatable btn-icon btn-transparent-dark me-2" href="' . website_base_url . 'news_voy_item.php?id=' . $article["id"] . '" title="View" target="_blank"><i data-feather="eye"></i></a>';
        $article["buttons"] .= '<a class="btn btn-datatable btn-icon btn-transparent-dark me-2" href="' . website_base_url . 'admin/news_voy/edit.php?id=' . $article["id"] . '" title="Edit"><i data-feather="edit"></i></a>';
        if ((int)($article["is_public"] ?? 0) === 1) {
            $article["buttons"] .= '<a class="btn btn-datatable btn-icon btn-transparent-dark me-2" href="' . website_base_url . 'admin/news_voy/social.php?id=' . (int)$article["id"] . '" title="Anteprima social" aria-label="Anteprima social"><i data-feather="share-2"></i></a>';
        }
        $article["buttons"] .= '<a class="btn btn-datatable btn-icon btn-transparent-dark me-2" href="#" data-bs-toggle="modal" data-bs-target="#deleteModal" title="Delete" data-id="' . $article["id"] . '"><i data-feather="trash-2"></i></a>';
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
                            News
                        </h1>
                    </div>
                    <div class="col-auto mb-3">
                        <a class="btn btn-outline-secondary me-2"
                            href="<?php echo website_base_url; ?>admin/news_voy/index_raw.php">
                            Raw HTML
                        </a>
                        <a class="btn btn-primary me-2"
                            href="<?php echo website_base_url; ?>admin/news_voy/index_raw.php?template=news4voy">
                            NEWS4VOY
                        </a>
                        <a class="btn btn-primary"
                            href="<?php echo website_base_url; ?>admin/news_voy/index_raw.php?template=bacheca">
                            Bacheca di Compagnia
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <!-- Main page content-->
    <div class="container-xl px-4">
        <div class="card mb-4">
            <div class="card-header">Manage News Articles</div>
            <div class="card-body">
                <table class="table-bordered" id="articles" style="display:none;">
                    <thead>
                        <tr>
                            <th>Title</strong></th>
                            <th>Author</th>
                            <th>Posted Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">Create Article</div>
            <div class="card-body">
                <?php if ($status == "success") { ?>
                    <div class="alert alert-success alert-dismissible fade show">Article successfully published.
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
                                    echo 'An error occurred when creating the article. Please try again later.';
                                }
                            }
                            if ($status == 'required_fields') {
                                echo 'Please check all fields have been completed.';
                            }
                            ?>
                            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php } ?>
                <?php } ?>
                <form method="post" class="form" id="editorForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="mb-1">Title*</label>
                        <input name="title" type="text" id="title" class="form-control" max-length="4"
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
                            <input name="poster" type="hidden" id="poster" value="<?php echo $_SESSION['name']; ?>" />
                            <button type="submit" id="submitButton" class="btn btn-primary">Save
                                Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal -->
    <div class="modal fade" id="deleteModal" data-bs-backdrop="static" tabindex="-1" role="dialog"
        aria-labelledby="staticBackdropLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="staticBackdropLabel">Delete Article</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">Are you sure you want to delete this article?</div>
                <div class="modal-footer"><button class="btn btn-light" type="button"
                        data-bs-dismiss="modal">Close</button><a href="#" class="btn btn-danger">Confirm</a>
                </div>
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
<script type="text/javascript">
    var dataSet = <?php echo json_encode($newsArticles); ?>;
    $(window).on('load', function() {
        $('#deleteModal').on('show.bs.modal', function(event) {
            var id = $(event.relatedTarget).data('id');
            $(this).find('.btn-danger').attr("href", "?function=delete_news&id=" + id);
        });
        if (!DataTable.isDataTable($('#articles').id)) {
            $('#articles').DataTable({
                data: dataSet,
                "pageLength": 25,
                columns: [{
                        data: "title",
                    },
                    {
                        data: "poster",
                    },
                    {
                        data: "date",
                    },
                    {
                        data: "buttons",
                        "orderable": false
                    }
                ],
                colReorder: false,
                "order": [
                    [2, 'desc']
                ],
                "drawCallback": function(settings) {
                    feather.replace();
                },
                "initComplete": function(settings, json) {
                    $(this).show()
                },
            });
        }
    });
</script>
<?php include '../includes/footer.php'; ?>
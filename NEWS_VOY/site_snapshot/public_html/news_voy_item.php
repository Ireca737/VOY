<?php
include 'lib/functions.php';
include 'config.php';

session_start();

require_once __DIR__ . '/voy/secure_config/conn_voy.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$article = null;

if ($id <= 0) {
    header('Location: ' . website_base_url);
    die();
}

$stmt = $conn->prepare(
    "SELECT id, title, poster, date, news
     FROM voy_news
     WHERE id = ?"
);

if (!$stmt) {
    error_log("news_voy_item.php prepare: " . $conn->error);
    header('Location: ' . website_base_url);
    die();
}

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$article = $result->fetch_assoc();

$stmt->close();

if (!$article) {
    header('Location: ' . website_base_url);
    die();
}


/* Incremento visualizzazioni */

$updateViews = $conn->prepare(
    "UPDATE voy_news
     SET views = views + 1
     WHERE id = ?"
);

if ($updateViews) {
    $updateViews->bind_param("i", $id);
    $updateViews->execute();
    $updateViews->close();
}
?>


<?php
$MetaPageTitle = "";
$MetaPageDescription = "";
$MetaPageKeywords = "";
?>

<?php include 'includes/header.php'; ?>

<section id="content" class="cp section offset-header">
    <div class="container">

        <div class="row">
            <div class="col-md-12">

                <h2 class="title">
                    <?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?>
                </h2>

                <span class="news-posted-by-main">
                    posted by
                    <?php echo htmlspecialchars($article['poster'], ENT_QUOTES, 'UTF-8'); ?>
                    on
                    <?php echo (new DateTime($article['date']))->format('d M Y'); ?>
                </span>

            </div>
        </div>

        <div class="row">
            <br />

            <div class="col-md-12 article-content">
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                &nbsp;
            </div>
        </div>

    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/editorjs-parser@1/build/Parser.browser.min.js"></script>

<script type="text/javascript">

var articleJson = <?php echo json_encode(
    $article['news'],
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_AMP |
    JSON_HEX_APOS |
    JSON_HEX_QUOT
); ?>;

$(window).on('load', function() {

    try {

        var articleData = JSON.parse(articleJson);
        var container = $(".article-content");

        container.empty();

        if (
            articleData.blocks &&
            Array.isArray(articleData.blocks)
        ) {

            articleData.blocks.forEach(function(block) {

                /*
                 * RAW HTML
                 *
                 * Contenuto inserito dagli amministratori VOY.
                 * Viene renderizzato direttamente.
                 */
                if (
                    block.type === "raw" &&
                    block.data &&
                    typeof block.data.html === "string"
                ) {

                    container.append(block.data.html);
                    return;
                }

                /*
                 * Tutti gli altri blocchi Editor.js
                 */
                try {

                    var parser = new edjsParser({
                        embed: {
                            useProvidedLength: false
                        }
                    });

                    var singleBlock = {
                        time: articleData.time || 0,
                        blocks: [block],
                        version: articleData.version || "2.30.7"
                    };

                    var html = parser.parse(singleBlock);

                    container.append(html);

                } catch (blockError) {

                    console.error(
                        "Errore parsing blocco:",
                        blockError
                    );

                }

            });

        }

    } catch (e) {

        console.error(
            "Errore parsing News_VOY:",
            e
        );

        $(".article-content").text(articleJson);

    }

});

</script>

<?php include 'includes/footer.php'; ?>
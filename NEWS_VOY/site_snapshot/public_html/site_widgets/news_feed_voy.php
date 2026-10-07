<?php
/*
 * VOY - News Feed Widget
 *
 * Uso:
 *   Pilot Centre:
 *      include_once '../site_widgets/news_feed.php';
 *
 *   Home pubblica:
 *      $newsFeedPublicOnly = true;
 *      include_once 'site_widgets/news_feed.php';
 *
 * Se $newsFeedPublicOnly non viene definito, il widget mostra tutte le news.
 */

require_once __DIR__ . '/../voy/secure_config/conn_voy.php';

$newsFeedPublicOnly = isset($newsFeedPublicOnly) ? (bool)$newsFeedPublicOnly : false;
$newsFeedLimit = 5;

$sql = "
    SELECT id, title, teaser, poster, date, is_public, views
    FROM voy_news
";

if ($newsFeedPublicOnly) {
    $sql .= " WHERE is_public = 1 ";
}

$sql .= " ORDER BY date DESC, id DESC LIMIT " . (int)$newsFeedLimit;

$newsFeedItems = [];

if ($result = $conn->query($sql)) {
    while ($row = $result->fetch_assoc()) {
        $newsFeedItems[] = $row;
    }
    $result->free();
}

/**
 * Converte il teaser redazionale:
 * -Prima voce
 * -Seconda voce
 *
 * in un array di voci pulite.
 */
function voyNewsTeaserItems(?string $teaser): array
{
    if ($teaser === null || trim($teaser) === '') {
        return [];
    }

    $lines = preg_split('/\R/u', $teaser);
    $items = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (strpos($line, '-') === 0) {
            $line = ltrim(substr($line, 1));
        }

        if ($line !== '') {
            $items[] = $line;
        }
    }

    return $items;
}
?>

<style>
.voy-news-widget {
    position: relative;
    width: calc(100% - 30px);
    max-width: 1170px;
    margin: 24px auto;
    background: #ffffff;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-shadow: 0 1px 2px rgba(0,0,0,.05);
    overflow: hidden;
}

.voy-news-widget-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px;
    margin-bottom: 0;
    border-bottom: 1px solid #ddd;
}

.voy-news-widget-title {
    margin: 0;
    font-size: 22px;
    font-weight: 600;
    color: #1f2d3d;
}

.voy-news-widget-nav {
    display: flex;
    gap: 8px;
}

.voy-news-widget-nav button {
    width: 42px;
    height: 42px;
    border: 1px solid #137eae;
    border-radius: 50%;
    background: #ffffff;
    color: #137eae;
    font-size: 20px;
    line-height: 30px;
    cursor: pointer;
    transition: background .2s ease, border-color .2s ease;
}

.voy-news-widget-nav button:hover {
    background: #f2f8fb;
    border-color: #9ec9dd;
}

.voy-news-track {
    display: flex;
    gap: 18px;
    overflow-x: auto;
    scroll-behavior: smooth;
    scroll-snap-type: x mandatory;
    padding: 2px 2px 14px 2px;
    scrollbar-width: thin;
}

.voy-news-card {
    flex: 0 0 calc((100% - 36px) / 3);
    min-width: 0;
    min-height: 320px;
    display: flex;
    flex-direction: column;
    scroll-snap-align: start;
    background: #ffffff;
    border: 1px solid #e1e6ea;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 3px 12px rgba(0,0,0,.06);
}

.voy-news-card-head {
    padding: 18px 22px;
    background: #f5f8fa;
    border-bottom: 3px solid #137eae;
}

.voy-news-card-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    padding: 18px;
}

.voy-news-card-title {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.25;
    color: #1f2d3d;
}

.voy-news-card-meta {
    margin-bottom: 16px;
    font-size: 12px;
    color: #8795a1;
}

.voy-news-teaser {
    margin: 0;
    padding: 0;
    list-style: none;
}

.voy-news-teaser li {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 9px;
    font-size: 14px;
    line-height: 1.45;
    color: #46535f;
}

.voy-news-teaser-mark {
    width: 11px;
    height: 4px;
    margin-top: 8px;
    flex: 0 0 11px;
    background: #137eae;
    border-radius: 1px;
}

.voy-news-card-footer {
    margin-top: auto;
    padding-top: 18px;
}

.voy-news-readmore {
    display: inline-block;
    font-size: 13px;
    font-weight: 600;
    color: #137eae;
    text-decoration: none;
}

.voy-news-readmore:hover {
    color: #0e6288;
    text-decoration: none;
}

.voy-news-empty {
    padding: 22px;
    background: #fff;
    border: 1px solid #e1e6ea;
    border-radius: 8px;
    color: #6c757d;
}

@media (max-width: 991px) {
    .voy-news-card {
        flex-basis: calc((100% - 18px) / 2);
    }
}

@media (max-width: 767px) {
    .voy-news-card {
        flex-basis: 88%;
    }

    .voy-news-widget-title {
        font-size: 20px;
    }
}
</style>

<div class="container voy-news-widget">

    <div class="voy-news-widget-header">
        <h3 class="voy-news-widget-title">Le ultime da VOY</h3>

        <?php if (count($newsFeedItems) > 1) { ?>
            <div class="voy-news-widget-nav" aria-label="Scorri le news">
                <button type="button" class="voy-news-prev" aria-label="News precedenti">&#8249;</button>
                <button type="button" class="voy-news-next" aria-label="News successive">&#8250;</button>
            </div>
        <?php } ?>
    </div>

    <?php if (!empty($newsFeedItems)) { ?>

        <div class="voy-news-track">

            <?php foreach ($newsFeedItems as $news) {
                $teaserItems = voyNewsTeaserItems($news['teaser'] ?? '');
            ?>

                <article class="voy-news-card">

                    <div class="voy-news-card-head">
                        <h4 class="voy-news-card-title">
                            <?php echo htmlspecialchars($news['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </h4>
                    </div>

                    <div class="voy-news-card-body">

                        <div class="voy-news-card-meta">
                            <?php echo htmlspecialchars($news['poster'], ENT_QUOTES, 'UTF-8'); ?>
                            &nbsp;&bull;&nbsp;
                            <?php echo (new DateTime($news['date']))->format('d M Y'); ?>
                            &nbsp;&bull;&nbsp;
                            &#128065;&nbsp;<?php echo (int)$news['views']; ?>
                        </div>

                        <?php if (!empty($teaserItems)) { ?>
                            <ul class="voy-news-teaser">
                                <?php foreach ($teaserItems as $item) { ?>
                                    <li>
                                        <span class="voy-news-teaser-mark" aria-hidden="true"></span>
                                        <span><?php echo htmlspecialchars($item, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </li>
                                <?php } ?>
                            </ul>
                        <?php } ?>

                        <div class="voy-news-card-footer">
                            <a
                                class="voy-news-readmore"
                                href="<?php echo website_base_url; ?>news_voy_item.php?id=<?php echo (int)$news['id']; ?>"
                            >
                                Leggi di pi&ugrave; &rarr;
                            </a>
                        </div>

                    </div>
                </article>

            <?php } ?>

        </div>

    <?php } else { ?>

        <div class="voy-news-empty">
            Al momento non ci sono news da mostrare.
        </div>

    <?php } ?>

</div>

<script>
(function () {
    var widgets = document.querySelectorAll('.voy-news-widget');

    widgets.forEach(function (widget) {
        var track = widget.querySelector('.voy-news-track');
        var prev = widget.querySelector('.voy-news-prev');
        var next = widget.querySelector('.voy-news-next');

        if (!track) {
            return;
        }

        function getStep() {
            var card = track.querySelector('.voy-news-card');

            if (!card) {
                return track.clientWidth;
            }

            var style = window.getComputedStyle(track);
            var gap = parseFloat(style.gap || style.columnGap || 18);

            return card.getBoundingClientRect().width + gap;
        }

        if (prev) {
            prev.addEventListener('click', function () {
                track.scrollBy({
                    left: -getStep(),
                    behavior: 'smooth'
                });
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                track.scrollBy({
                    left: getStep(),
                    behavior: 'smooth'
                });
            });
        }
    });
})();
</script>
<?php
/*
 * NEWS VOY - integrazione statistiche dashboard admin.
 *
 * Frammento di riferimento: non è una pagina PHP autonoma.
 * Richiede la connessione mysqli $conn già disponibile.
 */

$newsSummary = [
    'total_articles' => 0,
    'total_views' => 0
];

$newsTop = [];

$resultNewsSummary = $conn->query("
    SELECT
        COUNT(*) AS total_articles,
        COALESCE(SUM(views), 0) AS total_views
    FROM voy_news
");

if ($resultNewsSummary) {
    $newsSummary = $resultNewsSummary->fetch_assoc();
}

$resultNewsTop = $conn->query("
    SELECT id, title, views
    FROM voy_news
    ORDER BY views DESC, date DESC
    LIMIT 5
");

if ($resultNewsTop) {
    while ($row = $resultNewsTop->fetch_assoc()) {
        $newsTop[] = $row;
    }
}
?>

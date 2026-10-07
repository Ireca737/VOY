<?php
/**
 * VOY News - accesso dati
 * Modulo extra VAbase: usa il database VOY condiviso.
 */

require_once __DIR__ . '/../../voy/secure_config/conn_voy.php';

function voyNewsGetAll(): array
{
    global $conn;

    $sql = "SELECT id, title, teaser, poster, date, news, is_public
            FROM voy_news
            ORDER BY date DESC, id DESC";

    $result = $conn->query($sql);
    if ($result === false) {
        throw new RuntimeException($conn->error);
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function voyNewsGetById(int $id): ?array
{
    global $conn;

    $stmt = $conn->prepare(
        "SELECT id, title, teaser, poster, date, news, is_public
         FROM voy_news
         WHERE id = ?"
    );
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function voyNewsCreate(string $title, string $teaser, string $poster, string $news, int $isPublic = 0): int
{
    global $conn;

    $stmt = $conn->prepare(
        "INSERT INTO voy_news (title, teaser, poster, news, is_public)
         VALUES (?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $stmt->bind_param("ssssi", $title, $teaser, $poster, $news, $isPublic);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();

    return $id;
}

function voyNewsUpdate(int $id, string $title, string $teaser, string $news, int $isPublic = 0): bool
{
    global $conn;

    $stmt = $conn->prepare(
        "UPDATE voy_news
         SET title = ?, teaser = ?, news = ?, is_public = ?
         WHERE id = ?"
    );
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $stmt->bind_param("sssii", $title, $teaser, $news, $isPublic, $id);
    $stmt->execute();
    $ok = ($stmt->affected_rows >= 0);
    $stmt->close();

    return $ok;
}

function voyNewsDelete(int $id): bool
{
    global $conn;

    $stmt = $conn->prepare("DELETE FROM voy_news WHERE id = ?");
    if (!$stmt) {
        throw new RuntimeException($conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $ok = ($stmt->affected_rows > 0);
    $stmt->close();

    return $ok;
}
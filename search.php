<?php

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$query = trim($_GET['q'] ?? '');

if ($query === '') {
    echo json_encode([
        'success' => true,
        'query' => '',
        'results' => []
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
 * Оставляем только слова и цифры.
 * Это предотвращает передачу опасных SQL-конструкций
 * и лишних операторов FULLTEXT-поиска.
 */
$searchQuery = preg_replace(
    '/[^\p{L}\p{N}_]+/u',
    ' ',
    $query
);

$words = preg_split(
    '/\s+/u',
    trim($searchQuery),
    -1,
    PREG_SPLIT_NO_EMPTY
);

$words = array_slice($words, 0, 10);

if (count($words) === 0) {
    echo json_encode([
        'success' => true,
        'query' => $query,
        'results' => []
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$fulltextQuery = implode(
    ' ',
    array_map(
        static fn(string $word): string => '+' . $word . '*',
        $words
    )
);

$sql = '
    SELECT
        id,
        file_path,
        file_name,
        title,
        content,
        MATCH(title, content)
        AGAINST(? IN BOOLEAN MODE) AS relevance
    FROM documents
    WHERE MATCH(title, content)
        AGAINST(? IN BOOLEAN MODE)
    ORDER BY relevance DESC, title ASC
    LIMIT 100
';

$stmt = $db->prepare($sql);

$stmt->bind_param(
    'ss',
    $fulltextQuery,
    $fulltextQuery
);

$stmt->execute();

$result = $stmt->get_result();

$items = [];

while ($row = $result->fetch_assoc()) {
    $content = trim($row['content']);

    $snippet = mb_substr($content, 0, 300, 'UTF-8');

    if (mb_strlen($content, 'UTF-8') > 300) {
        $snippet .= '...';
    }

    $items[] = [
        'id' => (int) $row['id'],
        'file_path' => $row['file_path'],
        'file_name' => $row['file_name'],
        'title' => $row['title'],
        'snippet' => $snippet,
        'relevance' => round((float) $row['relevance'], 3)
    ];
}

$stmt->close();
$db->close();

echo json_encode([
    'success' => true,
    'query' => $query,
    'count' => count($items),
    'results' => $items
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

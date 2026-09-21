<?php

require_once __DIR__ . '/config.php';

$documentsDirectory = __DIR__ . '/documents';

if (!is_dir($documentsDirectory)) {
    exit("Папка documents не найдена\n");
}

function extractHtmlData(string $filePath): array
{
    libxml_use_internal_errors(true);

    $html = file_get_contents($filePath);

    $dom = new DOMDocument();

    $dom->loadHTML(
        '<?xml encoding="UTF-8">' . $html,
        LIBXML_NOERROR | LIBXML_NOWARNING
    );

    $title = '';

    $titleNodes = $dom->getElementsByTagName('title');

    if ($titleNodes->length > 0) {
        $title = trim($titleNodes->item(0)->textContent);
    }

    $bodyText = '';

    $bodyNodes = $dom->getElementsByTagName('body');

    if ($bodyNodes->length > 0) {
        $bodyText = $bodyNodes->item(0)->textContent;
    } else {
        $bodyText = $dom->textContent;
    }

    $bodyText = html_entity_decode(
        $bodyText,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );

    $bodyText = preg_replace('/\s+/u', ' ', $bodyText);
    $bodyText = trim($bodyText);

    return [
        'title' => $title,
        'content' => $bodyText
    ];
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $documentsDirectory,
        FilesystemIterator::SKIP_DOTS
    )
);

$insertQuery = $db->prepare(
    'INSERT INTO documents
        (file_path, file_name, title, content)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
        file_name = VALUES(file_name),
        title = VALUES(title),
        content = VALUES(content),
        updated_at = CURRENT_TIMESTAMP'
);

$count = 0;

foreach ($iterator as $fileInfo) {
    if (!$fileInfo->isFile()) {
        continue;
    }

    $extension = strtolower($fileInfo->getExtension());

    if (!in_array($extension, ['html', 'htm'], true)) {
        continue;
    }

    $absolutePath = $fileInfo->getPathname();

    $relativePath = str_replace(
        $documentsDirectory . DIRECTORY_SEPARATOR,
        '',
        $absolutePath
    );

    $relativePath = str_replace(
        DIRECTORY_SEPARATOR,
        '/',
        $relativePath
    );

    $fileName = basename($relativePath);

    $htmlData = extractHtmlData($absolutePath);

    $insertQuery->bind_param(
        'ssss',
        $relativePath,
        $fileName,
        $htmlData['title'],
        $htmlData['content']
    );

    $insertQuery->execute();

    $count++;

    echo "Обработан файл: {$relativePath}\n";
}

$insertQuery->close();
$db->close();

echo "\nИндексация завершена. Файлов обработано: {$count}\n";

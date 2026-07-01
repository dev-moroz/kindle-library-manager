<?php
    declare(strict_types=1);

    $booksDir = realpath(__DIR__ . '/../books/');
    $perPage = 10;
    $lengthTitle = 90;

    $allowedExtensions = [
        'epub',
        'fb2',
        'fb2.zip',
        'mobi',
        'azw',
        'azw3',
        'pdf',
        'djvu',
        'txt',
    ];

    function isAllowedBook(string $filename, array $allowedExtensions): bool
    {
        $name = strtolower($filename);

        foreach ($allowedExtensions as $ext) {
            if (str_ends_with($name, '.' . $ext)) {
                return true;
            }
        }

        return false;
    }

    function getTitle(string $filename): string
    {
        $title = preg_replace('/\.(fb2\.zip|epub|fb2|mobi|azw3|azw|pdf|djvu|txt)$/i', '', $filename);
        return $title ?: $filename;
    }


    function getExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }


    function truncateTitle(string $title, int $limit = 50): string
    {
        if (mb_strlen($title) > $limit) {
            return mb_substr($title, 0, $limit - 3) . '...';
        }
        return $title;
    }

    if (isset($_GET['download'])) {
        $requestedFile = basename((string)$_GET['download']);
        $filePath = $booksDir . '/' . $requestedFile;

        if ($requestedFile === '' || !is_file($filePath) || !isAllowedBook($requestedFile, $allowedExtensions)) {
            http_response_code(404);
            echo 'File not found';
            exit;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . rawurlencode($requestedFile) . '"');
        header('Content-Length: ' . (string)filesize($filePath));

        readfile($filePath);
        exit;
    }

    $books = [];

    if (is_dir($booksDir)) {
        $files = scandir($booksDir);

        if ($files !== false) {
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $path = $booksDir . '/' . $file;

                if (is_file($path) && isAllowedBook($file, $allowedExtensions)) {
                    $books[] = [
                        'filename' => $file,
                        'title' => truncateTitle(getTitle($file), $lengthTitle),
                        'extension' => getExtension($file),
                        'mtime' => filemtime($path) ?: 0,
                    ];
                }
            }
        }
    }

    usort($books, static function (array $a, array $b): int {
        return $b['mtime'] <=> $a['mtime'];
    });

    $totalBooks = count($books);
    $totalPages = max(1, (int)ceil($totalBooks / $perPage));
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    $currentBooks = array_slice($books, $offset, $perPage);
?>

<!doctype html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <style><?php include 'style.css'; ?></style>
        <title>Books</title>
    </head>
    <body>
    <h1>Books</h1>

    <?php if ($totalBooks === 0): ?>
    <p>No books</p>
    <?php else: ?>
        <?php foreach ($currentBooks as $book): ?>
            <div class="item">
                <a class="title" href="?download=<?php echo rawurlencode($book['filename']); ?>">
                    <?php echo htmlspecialchars($book['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </a>
                <span class="extention">
                    <?php echo htmlspecialchars($book['extension'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </span>
            </div>
        <?php endforeach; ?>

        <?php if ($totalPages > 1): ?>
            <div class="nav">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>">Назад</a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>">Вперёд</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </body>
</html>
